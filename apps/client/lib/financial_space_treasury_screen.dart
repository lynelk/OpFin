import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/services/financial_spaces_api.dart';

class FinancialSpaceStatementsScreen extends StatefulWidget {
  const FinancialSpaceStatementsScreen({super.key, required this.space});
  final Map<String, dynamic> space;

  @override
  State<FinancialSpaceStatementsScreen> createState() =>
      _FinancialSpaceStatementsScreenState();
}

class _FinancialSpaceStatementsScreenState
    extends State<FinancialSpaceStatementsScreen> {
  late Future<Map<String, dynamic>> _state;
  final NumberFormat _money = NumberFormat('#,##0', 'en_US');
  int? _accountId;

  int get spaceId => (widget.space['id'] as num).toInt();
  bool get canManage => const {
        'owner',
        'administrator',
        'admin',
        'chairperson',
        'treasurer',
        'secretary',
        'director',
        'manager',
      }.contains(widget.space['role']?.toString());

  @override
  void initState() {
    super.initState();
    _state = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final accounts = await FinancialSpacesApi.treasuryAccounts(spaceId);
    _accountId ??=
        accounts.isEmpty ? null : (accounts.first['id'] as num).toInt();
    final statements = _accountId == null
        ? <Map<String, dynamic>>[]
        : await FinancialSpacesApi.generatedStatements(
            spaceId,
            accountId: _accountId,
          );
    final allStatements = await FinancialSpacesApi.generatedStatements(spaceId);
    final consolidated = allStatements
        .where((statement) => statement['statement_scope'] == 'consolidated')
        .toList();
    return {
      'accounts': accounts,
      'statements': statements,
      'consolidated': consolidated,
    };
  }

  Future<void> _refresh() async {
    setState(() => _state = _load());
    await _state;
  }

  String _amount(dynamic value, String currency) {
    final number =
        value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;
    return currency + ' ' + _money.format(number);
  }

  Future<void> _issueStatement() async {
    if (_accountId == null) return;
    final now = DateTime.now();
    var from = DateTime(now.year, now.month, 1);
    var to = DateTime(now.year, now.month + 1, 0);

    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setLocal) => AlertDialog(
          title: const Text('Issue statement'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('From'),
                subtitle: Text(DateFormat('d MMM y').format(from)),
                onTap: () async {
                  final picked = await showDatePicker(
                    context: dialogContext,
                    firstDate: DateTime(2000),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                    initialDate: from,
                  );
                  if (picked != null) setLocal(() => from = picked);
                },
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('To'),
                subtitle: Text(DateFormat('d MMM y').format(to)),
                onTap: () async {
                  final picked = await showDatePicker(
                    context: dialogContext,
                    firstDate: from,
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                    initialDate: to.isBefore(from) ? from : to,
                  );
                  if (picked != null) setLocal(() => to = picked);
                },
              ),
              const Text(
                'Issued statements are frozen snapshots. Later corrections create a new statement.',
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Issue'),
            ),
          ],
        ),
      ),
    );

    if (ok != true) return;

    try {
      final statement = await FinancialSpacesApi.issueStatement(
        spaceId,
        _accountId!,
        DateFormat('yyyy-MM-dd').format(from),
        DateFormat('yyyy-MM-dd').format(to),
      );
      if (!mounted) return;
      await _refresh();
      await _openStatement((statement['id'] as num).toInt());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _issueConsolidatedStatement() async {
    final now = DateTime.now();
    var from = DateTime(now.year, now.month, 1);
    var to = DateTime(now.year, now.month + 1, 0);

    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setLocal) => AlertDialog(
          title: const Text('Issue consolidated statement'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'This statement includes all treasury accounts and the recorded Financial Space position. Different currencies remain separate.',
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('From'),
                subtitle: Text(DateFormat('d MMM y').format(from)),
                onTap: () async {
                  final picked = await showDatePicker(
                    context: dialogContext,
                    firstDate: DateTime(2000),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                    initialDate: from,
                  );
                  if (picked != null) setLocal(() => from = picked);
                },
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('To'),
                subtitle: Text(DateFormat('d MMM y').format(to)),
                onTap: () async {
                  final picked = await showDatePicker(
                    context: dialogContext,
                    firstDate: from,
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                    initialDate: to.isBefore(from) ? from : to,
                  );
                  if (picked != null) setLocal(() => to = picked);
                },
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Issue'),
            ),
          ],
        ),
      ),
    );

    if (ok != true) return;

    try {
      final statement = await FinancialSpacesApi.issueConsolidatedStatement(
        spaceId,
        DateFormat('yyyy-MM-dd').format(from),
        DateFormat('yyyy-MM-dd').format(to),
      );
      if (!mounted) return;
      await _refresh();
      await _openStatement((statement['id'] as num).toInt());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _openStatement(int statementId) async {
    try {
      final data =
          await FinancialSpacesApi.generatedStatement(spaceId, statementId);
      if (!mounted) return;
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => BankStyleStatementDetailScreen(data: data),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Treasury & statements')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _state,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(child: Text(snapshot.error.toString()));
            }

            final data = snapshot.data ?? const <String, dynamic>{};
            final accounts = (data['accounts'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .toList();
            final statements = (data['statements'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .toList();
            final consolidated = (data['consolidated'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .toList();

            if (accounts.isEmpty) {
              return ListView(
                padding: const EdgeInsets.all(20),
                children: const [
                  Text(
                    'No treasury account is configured yet. An authorised club officer can set one up in OpFin Workspace.',
                  ),
                ],
              );
            }

            final account = accounts.firstWhere(
              (item) => (item['id'] as num).toInt() == _accountId,
              orElse: () => accounts.first,
            );
            final currency = account['currency']?.toString() ?? 'UGX';

            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text(
                    'Club treasury',
                    style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'See book balances and issued statements. External statement import and reconciliation remain finance-administration tasks in OpFin Workspace.',
                  ),
                  const SizedBox(height: 18),
                  DropdownButtonFormField<int>(
                    initialValue: _accountId,
                    decoration:
                        const InputDecoration(labelText: 'Treasury account'),
                    items: accounts
                        .map(
                          (item) => DropdownMenuItem<int>(
                            value: (item['id'] as num).toInt(),
                            child: Text(
                              item['account_name']?.toString() ??
                                  'Treasury account',
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (value) async {
                      if (value == null || value == _accountId) return;
                      _accountId = value;
                      await _refresh();
                    },
                  ),
                  const SizedBox(height: 12),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            account['account_name']?.toString() ??
                                'Treasury account',
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (account['institution_name'] != null)
                            Text(account['institution_name'].toString()),
                          if (account['account_reference_masked'] != null)
                            Text(account['account_reference_masked'].toString()),
                          const SizedBox(height: 12),
                          const Text('Book balance'),
                          Text(
                            _amount(
                              account['current_balance_minor'],
                              currency,
                            ),
                            style: const TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'All activity',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 6),
                  if (canManage)
                    FilledButton.icon(
                      onPressed: _issueConsolidatedStatement,
                      icon: const Icon(Icons.account_balance_outlined),
                      label: const Text('Issue consolidated all-activity statement'),
                    ),
                  if (consolidated.isNotEmpty)
                    ...consolidated.take(6).map(
                      (statement) => Card(
                        child: ListTile(
                          leading: const Icon(Icons.library_books_outlined),
                          title: Text(
                            statement['statement_number']?.toString() ??
                                'Consolidated statement',
                          ),
                          subtitle: Text(
                            (statement['period_start']?.toString() ?? '') +
                                ' to ' +
                                (statement['period_end']?.toString() ?? ''),
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => _openStatement(
                            (statement['id'] as num).toInt(),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 16),
                  const Text(
                    'Account statement',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  if (canManage)
                    FilledButton.icon(
                      onPressed: _issueStatement,
                      icon: const Icon(Icons.description_outlined),
                      label: const Text('Issue account statement'),
                    )
                  else
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(14),
                        child: Text(
                          'Statements are issued by authorised club officers. Members can view every issued statement here.',
                        ),
                      ),
                    ),
                  const SizedBox(height: 20),
                  const Text(
                    'Issued statements',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  if (statements.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('No statement has been issued yet.'),
                      ),
                    )
                  else
                    ...statements.map(
                      (statement) => Card(
                        child: ListTile(
                          leading: const Icon(Icons.receipt_long_outlined),
                          title: Text(
                            statement['statement_number']?.toString() ??
                                'Statement',
                          ),
                          subtitle: Text(
                            (statement['period_start']?.toString() ?? '') +
                                ' to ' +
                                (statement['period_end']?.toString() ?? '') +
                                ' · ' +
                                (statement['reconciliation_status']
                                            ?.toString() ??
                                        'unreconciled')
                                    .replaceAll('_', ' '),
                          ),
                          trailing: Text(
                            _amount(
                              statement['closing_balance_minor'],
                              currency,
                            ),
                          ),
                          onTap: () => _openStatement(
                            (statement['id'] as num).toInt(),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            );
          },
        ),
      );
}

class BankStyleStatementDetailScreen extends StatelessWidget {
  const BankStyleStatementDetailScreen({super.key, required this.data});
  final Map<String, dynamic> data;

  String _money(dynamic value, String currency) {
    final number =
        value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;
    return currency +
        ' ' +
        NumberFormat('#,##0', 'en_US').format(number);
  }

  @override
  Widget build(BuildContext context) {
    final statement =
        (data['statement'] as Map?)?.cast<String, dynamic>() ?? {};
    final account = (data['account'] as Map?)?.cast<String, dynamic>() ?? {};
    final sections = (data['sections'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
    final totalsByCurrency =
        (data['totals_by_currency'] as Map?)?.cast<String, dynamic>() ?? {};
    final positionByCurrency =
        (data['position_by_currency'] as Map?)?.cast<String, dynamic>() ?? {};
    final rows = (data['rows'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
    final consolidated = statement['statement_scope'] == 'consolidated';
    final currency = account['currency']?.toString() ?? 'UGX';
    final hash = statement['content_hash']?.toString() ?? '';

    return Scaffold(
      appBar: AppBar(title: const Text('Statement')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text(
            'OpFin',
            style: TextStyle(fontSize: 28, fontWeight: FontWeight.w900),
          ),
          Text(
            consolidated
                ? 'Consolidated Financial Space Statement'
                : 'Financial Space Statement',
          ),
          const Divider(height: 30),
          Text(
            statement['statement_number']?.toString() ?? '',
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          Text(
            (statement['period_start']?.toString() ?? '') +
                ' to ' +
                (statement['period_end']?.toString() ?? ''),
          ),
          const SizedBox(height: 14),
          if (!consolidated)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      account['account_name']?.toString() ?? 'Treasury account',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    if (account['institution_name'] != null)
                      Text(account['institution_name'].toString()),
                    if (account['account_reference_masked'] != null)
                      Text(account['account_reference_masked'].toString()),
                  ],
                ),
              ),
            ),
          if (consolidated) ...[
            const Text(
              'Totals by currency',
              style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
            ),
            ...totalsByCurrency.entries.map(
              (entry) {
                final values = (entry.value as Map).cast<String, dynamic>();
                return Card(
                  child: ListTile(
                    title: Text(entry.key),
                    subtitle: Text(
                      'Opening ' +
                          _money(values['opening_balance_minor'], entry.key) +
                          ' · Closing ' +
                          _money(values['closing_balance_minor'], entry.key),
                    ),
                    trailing: Text(
                      ((values['transaction_count'] as num?)?.toInt() ?? 0)
                              .toString() +
                          ' txns',
                    ),
                  ),
                );
              },
            ),
            const SizedBox(height: 8),
            const Text(
              'Recorded financial position',
              style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
            ),
            ...positionByCurrency.entries.map(
              (entry) {
                final values = (entry.value as Map).cast<String, dynamic>();
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Text(
                      entry.key +
                          ': assets ' +
                          _money(values['recorded_assets_minor'], entry.key) +
                          ' · owed ' +
                          _money(values['amount_owed_minor'], entry.key) +
                          ' · receivable ' +
                          _money(values['amount_receivable_minor'], entry.key),
                    ),
                  ),
                );
              },
            ),
          ],
          if (!consolidated)
          Row(
            children: [
              Expanded(
                child: _Metric(
                  label: 'Opening',
                  value: _money(
                    statement['opening_balance_minor'],
                    currency,
                  ),
                ),
              ),
              Expanded(
                child: _Metric(
                  label: 'Closing',
                  value: _money(
                    statement['closing_balance_minor'],
                    currency,
                  ),
                ),
              ),
            ],
          ),
          if (!consolidated)
          Row(
            children: [
              Expanded(
                child: _Metric(
                  label: 'Debits',
                  value: _money(
                    statement['total_debits_minor'],
                    currency,
                  ),
                ),
              ),
              Expanded(
                child: _Metric(
                  label: 'Credits',
                  value: _money(
                    statement['total_credits_minor'],
                    currency,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Text(
            'Transactions',
            style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
          ),
          if (consolidated && sections.isNotEmpty)
            ...sections.map(
              (section) {
                final sectionAccount =
                    (section['account'] as Map?)?.cast<String, dynamic>() ?? {};
                final sectionRows = (section['rows'] as List? ?? const [])
                    .whereType<Map>()
                    .map((item) => item.cast<String, dynamic>())
                    .toList();
                final sectionCurrency =
                    sectionAccount['currency']?.toString() ?? 'UGX';
                return Card(
                  child: ExpansionTile(
                    title: Text(
                      sectionAccount['account_name']?.toString() ??
                          'Treasury account',
                    ),
                    subtitle: Text(
                      sectionCurrency +
                          ' · closing ' +
                          _money(
                            section['closing_balance_minor'],
                            sectionCurrency,
                          ),
                    ),
                    children: sectionRows
                        .map(
                          (row) => ListTile(
                            title: Text(
                              row['description']?.toString() ?? 'Transaction',
                            ),
                            subtitle: Text(
                              (row['date']?.toString() ?? '') +
                                  (row['reference'] == null
                                      ? ''
                                      : ' · ' + row['reference'].toString()),
                            ),
                            trailing: Text(
                              row['debit_minor'] != null
                                  ? '- ' +
                                      _money(
                                        row['debit_minor'],
                                        sectionCurrency,
                                      )
                                  : '+ ' +
                                      _money(
                                        row['credit_minor'],
                                        sectionCurrency,
                                      ),
                            ),
                          ),
                        )
                        .toList(),
                  ),
                );
              },
            )
          else if (rows.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Text('No transactions in this statement period.'),
              ),
            )
          else
            ...rows.map(
              (row) => Card(
                child: ListTile(
                  title: Text(row['description']?.toString() ?? 'Transaction'),
                  subtitle: Text(
                    (row['date']?.toString() ?? '') +
                        (row['reference'] == null
                            ? ''
                            : ' · ' + row['reference'].toString()),
                  ),
                  trailing: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        row['debit_minor'] != null
                            ? '- ' + _money(row['debit_minor'], currency)
                            : '+ ' + _money(row['credit_minor'], currency),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        _money(row['balance_minor'], currency),
                        style: const TextStyle(fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          const SizedBox(height: 18),
          Text(
            'Reconciliation: ' +
                (statement['reconciliation_status']?.toString() ??
                        'unreconciled')
                    .replaceAll('_', ' '),
          ),
          if (hash.isNotEmpty)
            Text(
              'Integrity: ' +
                  hash.substring(0, hash.length < 16 ? hash.length : 16) +
                  '…',
            ),
          const SizedBox(height: 18),
          const Text(
            'This is an OpFin Financial Space statement, not a bank statement issued by the underlying financial institution. Imported external statements remain separate reconciliation evidence.',
            style: TextStyle(fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label),
              Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      );
}
