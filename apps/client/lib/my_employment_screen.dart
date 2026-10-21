import 'package:flutter/material.dart';
import 'package:opfin/services/financial_spaces_api.dart';
import 'package:opfin/people_hub_screen.dart';
import 'package:opfin/workspace_access.dart';

class MyEmploymentScreen extends StatefulWidget {
  const MyEmploymentScreen({super.key});

  @override
  State<MyEmploymentScreen> createState() => _MyEmploymentScreenState();
}

class _MyEmploymentScreenState extends State<MyEmploymentScreen> {
  late Future<List<Map<String, dynamic>>> _spaces;

  @override
  void initState() {
    super.initState();
    _spaces = _load();
  }

  static Future<List<Map<String, dynamic>>> _load() async =>
      (await FinancialSpacesApi.spaces())
          .where((space) => WorkspaceAccess.canOpenPeople(space)).toList();

  void _refresh() => setState(() => _spaces = _load());

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('My employment')),
    body: FutureBuilder<List<Map<String, dynamic>>>(
      future: _spaces,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Text('We could not check your employment connections.'),
            const SizedBox(height: 12),
            FilledButton(onPressed: _refresh, child: const Text('Try again')),
          ]));
        }
        final spaces = snapshot.data!;
        if (spaces.isEmpty) {
          return const Center(child: Padding(
            padding: EdgeInsets.all(24),
            child: Text('No linked employer is available in your OpFin account. Your financial account is unaffected.',
                textAlign: TextAlign.center),
          ));
        }
        return ListView(padding: const EdgeInsets.all(16), children: [
          const Text('Linked organisations', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          const Text('Select an authorised organisation to access your work records, leave, attendance and other available People services.'),
          const SizedBox(height: 16),
          ...spaces.map((space) => Card(child: ListTile(
            leading: const Icon(Icons.business_outlined),
            title: Text(space['name']?.toString() ?? 'Organisation'),
            subtitle: Text('Membership: ${space['role'] ?? 'member'}'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => PeopleHubScreen(space: space))),
          ))),
        ]);
      },
    ),
  );
}
