import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/services/financial_spaces_api.dart';
import 'package:opfin/workspace_access.dart';

/// Financial Space scoped Savings Groups actions. Finality is determined by
/// statement reconciliation on the server, never by a button or a local balance.
class SavingsGroupsWorkspaceScreen extends StatefulWidget {
  const SavingsGroupsWorkspaceScreen({super.key, required this.space});

  final Map<String, dynamic> space;

  @override
  State<SavingsGroupsWorkspaceScreen> createState() =>
      _SavingsGroupsWorkspaceScreenState();
}

class _SavingsGroupsWorkspaceScreenState extends State<SavingsGroupsWorkspaceScreen> {
  late Future<List<Map<String, dynamic>>> _cycles;
  final NumberFormat _money = NumberFormat('#,##0', 'en_US');

  int get spaceId => (widget.space['id'] as num).toInt();
  bool get official => WorkspaceAccess.canEditFinances(widget.space);

  @override
  void initState() {
    super.initState();
    _cycles = FinancialSpacesApi.savingsCycles(spaceId);
  }

  Future<void> _reload() async {
    setState(() => _cycles = FinancialSpacesApi.savingsCycles(spaceId));
    await _cycles;
  }

  Future<void> _perform(Future<void> Function() operation, String message) async {
    try {
      await operation();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
      await _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  Future<void> _newCycle() async {
    if (!official) return;
    final name = TextEditingController();
    final from = TextEditingController();
    final to = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Start a savings cycle'),
        content: SingleChildScrollView(child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: name,
                decoration: const InputDecoration(labelText: 'Cycle name')),
            TextField(controller: from,
                decoration: const InputDecoration(labelText: 'Start date (YYYY-MM-DD)')),
            TextField(controller: to,
                decoration: const InputDecoration(labelText: 'End date (YYYY-MM-DD)')),
            const SizedBox(height: 12),
            const Text('A second authorised official must activate the cycle.',
                style: TextStyle(fontSize: 12)),
          ],
        )),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true),
              child: const Text('Create draft')),
        ],
      ),
    );
    if (submitted == true && name.text.trim().isNotEmpty) {
      await _perform(() async {
        await FinancialSpacesApi.createSavingsCycle(
            spaceId, name.text.trim(), from.text.trim(), to.text.trim());
      }, 'Draft created. An independent official must approve activation.');
    }
    name.dispose();
    from.dispose();
    to.dispose();
  }

  Future<void> _contribute(int cycleId) async {
    final amount = TextEditingController();
    String kind = 'savings';
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setLocal) => AlertDialog(
          title: const Text('Record a contribution pledge'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            DropdownButtonFormField<String>(
              initialValue: kind,
              decoration: const InputDecoration(labelText: 'Purpose'),
              items: const [
                DropdownMenuItem(value: 'savings', child: Text('Savings')),
                DropdownMenuItem(value: 'social_fund', child: Text('Social fund')),
                DropdownMenuItem(value: 'fine', child: Text('Fine')),
              ],
              onChanged: (value) => setLocal(() => kind = value ?? kind),
            ),
            TextField(controller: amount, keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Amount (minor currency units)')),
            const SizedBox(height: 12),
            const Text('Pledge only: this does not confirm receipt or transfer money.',
                style: TextStyle(fontSize: 12)),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(dialogContext, true),
                child: const Text('Record pledge')),
          ],
        ),
      ),
    );
    final value = int.tryParse(amount.text.replaceAll(',', ''));
    amount.dispose();
    if (submitted != true || value == null || value <= 0) return;
    await _perform(() => FinancialSpacesApi.savingsPledge(spaceId, cycleId, kind, value),
        'Pledge recorded. An independent official must reconcile the payment.');
  }

  Future<void> _requestLoan(int cycleId) async {
    final amount = TextEditingController();
    final days = TextEditingController(text: '30');
    final rate = TextEditingController(text: '0');
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Request a member loan'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: amount, keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Principal (minor units)')),
          TextField(controller: days, keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Term (days)')),
          TextField(controller: rate, keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Interest rate (basis points)')),
          const SizedBox(height: 10),
          const Text('An approved request is not a loan disbursement.',
              style: TextStyle(fontSize: 12)),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Request')),
        ],
      ),
    );
    final a = int.tryParse(amount.text), d = int.tryParse(days.text),
        r = int.tryParse(rate.text);
    amount.dispose();
    days.dispose();
    rate.dispose();
    if (submitted != true || a == null || a <= 0 || d == null || r == null) return;
    await _perform(() => FinancialSpacesApi.savingsLoanRequest(spaceId, cycleId, a, d, r),
        'Loan request recorded for independent review. No funds have moved.');
  }

  Future<void> _requestSupport(int cycleId) async {
    final amount = TextEditingController();
    final reason = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Request social-fund support'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: amount, keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Amount (minor units)')),
          TextField(controller: reason,
              decoration: const InputDecoration(labelText: 'Reason')),
          const Text('Requests require approval and separate evidence of payout.',
              style: TextStyle(fontSize: 12)),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Request')),
        ],
      ),
    );
    final value = int.tryParse(amount.text);
    final rationale = reason.text.trim();
    amount.dispose();
    reason.dispose();
    if (submitted != true || value == null || value <= 0 || rationale.length < 10) return;
    await _perform(() => FinancialSpacesApi.savingsSocialClaim(
        spaceId, cycleId, value, rationale), 'Social-fund request recorded.');
  }

  Future<void> _showContributions(int cycleId) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) =>
        _ContributionListScreen(spaceId: spaceId, cycleId: cycleId, official: official)));
  }

  Future<void> _showShareouts(int cycleId) async {
    try {
      final entries = await FinancialSpacesApi.savingsShareouts(spaceId, cycleId);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (context) => SafeArea(child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.all(18),
          children: [
            const Text('Share-out proposals',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700)),
            const Text('Proposed savings principal only. No payouts are implied.'),
            if (entries.isEmpty) const Text('No allocations have been prepared.'),
            ...entries.map((entry) => ListTile(
              title: Text('Member ${entry['member_user_id']}'),
              subtitle: Text(entry['status']?.toString() ?? 'proposed'),
              trailing: Text(_money.format(entry['principal_minor'] ?? 0)),
            )),
          ],
        )),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Savings Groups')),
    floatingActionButton: official
        ? FloatingActionButton.extended(
            onPressed: _newCycle,
            icon: const Icon(Icons.add),
            label: const Text('New cycle'))
        : null,
    body: RefreshIndicator(onRefresh: _reload, child:
      FutureBuilder<List<Map<String, dynamic>>>(
        future: _cycles,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(children: const [
              Padding(padding: EdgeInsets.all(28),
                  child: Text('Savings cycles are unavailable. Pull down to retry.')),
            ]);
          }
          final cycles = snapshot.data ?? const <Map<String, dynamic>>[];
          return ListView(padding: const EdgeInsets.all(16), children: [
            Text(widget.space['name']?.toString() ?? 'Savings Group',
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 23)),
            const SizedBox(height: 8),
            const Text('Contributions are pledges until separately matched to '
                'statement evidence. Member loans, social funds and share-outs '
                'cannot be paid through these request screens.'),
            const SizedBox(height: 12),
            if (cycles.isEmpty)
              const Card(child: Padding(padding: EdgeInsets.all(18),
                  child: Text('No savings cycles yet. An official can create a draft.'))),
            ...cycles.map((cycle) {
              final status = cycle['status']?.toString() ?? 'draft';
              final id = (cycle['id'] as num).toInt();
              return Card(child: ExpansionTile(
                title: Text(cycle['name']?.toString() ?? 'Savings cycle'),
                subtitle: Text('$status · ${cycle['starts_on']} to ${cycle['ends_on']}'),
                childrenPadding: const EdgeInsets.all(8),
                children: [
                  if (official && status == 'draft')
                    ListTile(leading: const Icon(Icons.verified_user_outlined),
                        title: const Text('Activate with second official'),
                        onTap: () => _perform(
                            () => FinancialSpacesApi.activateSavingsCycle(spaceId, id),
                            'Savings cycle activated.')),
                  if (status == 'open') ...[
                    ListTile(leading: const Icon(Icons.savings_outlined),
                        title: const Text('Record my contribution pledge'),
                        onTap: () => _contribute(id)),
                    ListTile(leading: const Icon(Icons.handshake_outlined),
                        title: const Text('Request a member loan'),
                        onTap: () => _requestLoan(id)),
                    ListTile(leading: const Icon(Icons.volunteer_activism_outlined),
                        title: const Text('Request social-fund support'),
                        onTap: () => _requestSupport(id)),
                    if (official)
                      ListTile(leading: const Icon(Icons.lock_clock_outlined),
                        title: const Text('Close completed cycle'),
                        onTap: () => _perform(
                            () => FinancialSpacesApi.closeSavingsCycle(spaceId, id),
                            'Cycle closed for share-out review.')),
                  ],
                  ListTile(leading: const Icon(Icons.receipt_long_outlined),
                      title: const Text('Contribution ledger'),
                      onTap: () => _showContributions(id)),
                  if (status == 'closed' && official)
                    ListTile(leading: const Icon(Icons.account_balance_outlined),
                        title: const Text('Prepare share-out proposals'),
                        onTap: () => _perform(
                            () => FinancialSpacesApi.prepareSavingsShareout(spaceId, id),
                            'Share-out proposals prepared for second-official approval.')),
                  if (status == 'closed')
                    ListTile(leading: const Icon(Icons.fact_check_outlined),
                        title: const Text('View share-out allocations'),
                        onTap: () => _showShareouts(id)),
                ],
              ));
            }),
          ]);
        },
      ),
    ),
  );
}

class _ContributionListScreen extends StatefulWidget {
  const _ContributionListScreen({
    required this.spaceId, required this.cycleId, required this.official,
  });
  final int spaceId;
  final int cycleId;
  final bool official;

  @override
  State<_ContributionListScreen> createState() => _ContributionListScreenState();
}

class _ContributionListScreenState extends State<_ContributionListScreen> {
  late Future<Map<String, dynamic>> _data;

  @override
  void initState() {
    super.initState();
    _data = FinancialSpacesApi.savingsContributions(widget.spaceId, widget.cycleId);
  }

  Future<void> _refresh() async {
    setState(() => _data =
        FinancialSpacesApi.savingsContributions(widget.spaceId, widget.cycleId));
    await _data;
  }

  Future<void> _verify(int contributionId) async {
    final input = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Match contribution to a statement'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: input, keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Matched treasury transaction ID')),
          const SizedBox(height: 10),
          const Text('Only a separately reconciled transaction with the exact amount is accepted.',
              style: TextStyle(fontSize: 12)),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Verify match')),
        ],
      ),
    );
    final transactionId = int.tryParse(input.text);
    input.dispose();
    if (confirmed != true || transactionId == null) return;
    try {
      await FinancialSpacesApi.verifySavingsContribution(
          widget.spaceId, widget.cycleId, contributionId, transactionId);
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Contributions')),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _data,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(child: Text(snapshot.error.toString()));
        }
        final raw = snapshot.data?['contributions'];
        final entries = raw is Map ? raw['data'] as List? ?? const [] : const [];
        return RefreshIndicator(onRefresh: _refresh,
          child: ListView(padding: const EdgeInsets.all(16), children: [
            if (entries.isEmpty) const Text('No contributions recorded yet.'),
            ...entries.whereType<Map>().map((entry) => Card(child: ListTile(
              title: Text('${entry['kind']} · ${entry['amount_minor']}'),
              subtitle: Text('Member ${entry['member_user_id']} · ${entry['status']}'),
              trailing: widget.official && entry['status'] == 'pledged'
                  ? IconButton(
                      tooltip: 'Match transaction',
                      icon: const Icon(Icons.fact_check_outlined),
                      onPressed: () => _verify((entry['id'] as num).toInt()))
                  : const Icon(Icons.receipt_long_outlined),
            ))),
          ]),
        );
      },
    ),
  );
}
