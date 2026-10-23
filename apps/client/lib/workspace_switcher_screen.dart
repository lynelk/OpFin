import 'package:flutter/material.dart';
import 'package:opfin/services/financial_spaces_api.dart';
import 'package:opfin/workspace_access.dart';

/// Returns a server-authorised Financial Space to the calling screen.
/// Never persists selected IDs or credentials across signed-in accounts.
class WorkspaceSwitcherScreen extends StatefulWidget {
  const WorkspaceSwitcherScreen({super.key, this.loadSpaces});

  /// Optional loader injection for deterministic navigation/widget tests.
  final Future<List<Map<String, dynamic>>> Function()? loadSpaces;

  @override
  State<WorkspaceSwitcherScreen> createState() => _WorkspaceSwitcherScreenState();
}

class _WorkspaceSwitcherScreenState extends State<WorkspaceSwitcherScreen> {
  late Future<List<Map<String, dynamic>>> _spaces;

  @override
  void initState() {
    super.initState();
    _spaces = (widget.loadSpaces ?? FinancialSpacesApi.spaces)();
  }

  Future<void> _reload() async {
    setState(() => _spaces = (widget.loadSpaces ?? FinancialSpacesApi.spaces)());
    await _spaces;
  }

  IconData _icon(String? type) {
    switch (type) {
      case 'personal': return Icons.person_outline;
      case 'household': return Icons.home_outlined;
      case 'savings_group': return Icons.groups_outlined;
      case 'sacco': return Icons.account_balance_outlined;
      case 'investment_club': return Icons.trending_up_outlined;
      case 'business': return Icons.business_outlined;
      default: return Icons.account_balance_wallet_outlined;
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Switch workspace')),
    body: FutureBuilder<List<Map<String, dynamic>>>(
      future: _spaces,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.cloud_off_outlined, size: 40),
              const SizedBox(height: 12),
              const Text('We could not load your authorised workspaces.',
                  textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: _reload, child: const Text('Try again')),
            ]),
          ));
        }
        final spaces = snapshot.data ?? const <Map<String, dynamic>>[];
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView(padding: const EdgeInsets.all(16), children: [
            const Text('Your workspaces',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            const Text('Select a space to work with its own records. '
                'Your personal finances remain private.'),
            const SizedBox(height: 20),
            if (spaces.isEmpty)
              const Card(child: Padding(
                padding: EdgeInsets.all(20),
                child: Text('No active workspace is linked to this account.'),
              )),
            ...spaces.map((space) => Card(
              child: ListTile(
                key: ValueKey('workspace-${space['public_id'] ?? space['id']}'),
                isThreeLine: true,
                leading: CircleAvatar(child: Icon(_icon(space['type']?.toString()))),
                title: Text(space['name']?.toString() ?? WorkspaceAccess.label(space)),
                subtitle: Text('${WorkspaceAccess.label(space)} · ${WorkspaceAccess.role(space)}\n'
                    '${WorkspaceAccess.accessDescription(space)}'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => Navigator.pop(context, space),
              ),
            )),
            const SizedBox(height: 16),
            const Text('Access is checked again by OpFin for each action. '
                'A workspace membership does not grant access to somebody else’s personal finances.',
                style: TextStyle(fontSize: 12)),
          ]),
        );
      },
    ),
  );
}
