import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/services/financial_spaces_api.dart';
import 'package:opfin/workspace_access.dart';

class FinancialSpaceBudgetScreen extends StatefulWidget {
  const FinancialSpaceBudgetScreen({super.key, required this.space});

  final Map<String, dynamic> space;

  @override
  State<FinancialSpaceBudgetScreen> createState() =>
      _FinancialSpaceBudgetScreenState();
}

class _FinancialSpaceBudgetScreenState extends State<FinancialSpaceBudgetScreen> {
  late DateTime _month;
  late Future<Map<String, dynamic>> _data;
  final _numbers = NumberFormat('#,##0', 'en_US');

  int get spaceId => (widget.space['id'] as num).toInt();
  bool get canEdit => WorkspaceAccess.canEditFinances(widget.space);
  String get monthKey => DateFormat('yyyy-MM').format(_month);

  @override
  void initState() {
    super.initState();
    _month = DateTime(DateTime.now().year, DateTime.now().month);
    _data = FinancialSpacesApi.budgets(spaceId, month: monthKey);
  }

  Future<void> _reload() async {
    setState(() => _data = FinancialSpacesApi.budgets(spaceId, month: monthKey));
    await _data;
  }

  void _changeMonth(int step) {
    setState(() {
      _month = DateTime(_month.year, _month.month + step);
      _data = FinancialSpacesApi.budgets(spaceId, month: monthKey);
    });
  }

  Future<void> _create() async {
    if (!canEdit) return;
    final category = TextEditingController();
    final limit = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('New monthly budget'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(
            controller: category,
            maxLength: 120,
            decoration: const InputDecoration(labelText: 'Budget category'),
          ),
          TextField(
            controller: limit,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(
              labelText: 'Monthly limit (${widget.space['currency'] ?? 'UGX'})',
            ),
          ),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Save')),
        ],
      ),
    );
    final amount = int.tryParse(limit.text.replaceAll(',', '').trim());
    final name = category.text.trim();
    category.dispose();
    limit.dispose();

    if (submitted != true || name.isEmpty || amount == null || amount < 0) {
      if (submitted == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Enter a category and a valid monthly limit.')),
        );
      }
      return;
    }
    try {
      await FinancialSpacesApi.createBudget(spaceId,
        category: name, monthlyLimitMinor: amount,
        effectiveFrom: DateFormat('yyyy-MM-dd').format(_month));
      if (!mounted) return;
      await _reload();
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Budget could not be saved. Please retry.')),
        );
      }
    }
  }

  Future<void> _deactivate(int budgetId) async {
    if (!canEdit) return;
    try {
      await FinancialSpacesApi.setBudgetActive(spaceId, budgetId, false);
      if (!mounted) return;
      await _reload();
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to update this budget.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Budgets')),
    floatingActionButton: canEdit
        ? FloatingActionButton.extended(
            onPressed: _create, icon: const Icon(Icons.add),
            label: const Text('Add budget'))
        : null,
    body: RefreshIndicator(
      onRefresh: _reload,
      child: ListView(padding: const EdgeInsets.all(16), children: [
        Text(widget.space['name']?.toString() ?? 'Financial space',
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 12),
        Row(children: [
          IconButton(tooltip: 'Previous month',
              icon: const Icon(Icons.chevron_left),
              onPressed: () => _changeMonth(-1)),
          Expanded(child: Text(DateFormat('MMMM yyyy').format(_month),
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w700))),
          IconButton(tooltip: 'Next month',
              icon: const Icon(Icons.chevron_right),
              onPressed: () => _changeMonth(1)),
        ]),
        const SizedBox(height: 8),
        FutureBuilder<Map<String, dynamic>>(
          future: _data,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Padding(padding: EdgeInsets.all(50),
                  child: Center(child: CircularProgressIndicator()));
            }
            if (snapshot.hasError) {
              return const Card(child: Padding(
                padding: EdgeInsets.all(20),
                child: Text('Unable to load budgets. Pull down to retry.'),
              ));
            }
            final data = snapshot.data ?? const <String, dynamic>{};
            final budgets = (data['budgets'] as List? ?? const []);
            final currency = data['currency']?.toString() ?? 'UGX';
            if (budgets.isEmpty) {
              return const Card(child: Padding(
                padding: EdgeInsets.all(20),
                child: Text('No planned budgets for this month.'),
              ));
            }
            return Column(children: [
              ...budgets.whereType<Map>().map((row) => Card(
                child: ListTile(
                  leading: const Icon(Icons.pie_chart_outline),
                  title: Text(row['category']?.toString() ?? 'Category'),
                  subtitle: Text(
                    'Planned: $currency ${_numbers.format((row['monthly_limit_minor'] as num?)?.toInt() ?? 0)}'
                    '\\nStatement-matched: $currency ${_numbers.format((row['statement_matched_actual_minor'] as num?)?.toInt() ?? 0)}'
                    '\\n${row['over_planned_limit'] == true ? 'Over planned limit' : row['alert_threshold_reached'] == true ? 'Approaching planned limit' : 'Within planned limit'}',
                  ),
                  isThreeLine: true,
                  trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(row['over_planned_limit'] == true
                        ? Icons.warning_amber_outlined
                        : row['alert_threshold_reached'] == true
                            ? Icons.notifications_active_outlined
                            : Icons.check_circle_outline),
                    if (canEdit)
                      PopupMenuButton<String>(
                        tooltip: 'Budget actions',
                        onSelected: (action) {
                          if (action == 'deactivate') {
                            _deactivate((row['id'] as num).toInt());
                          }
                        },
                        itemBuilder: (_) => const [
                          PopupMenuItem(value: 'deactivate', child: Text('Deactivate')),
                        ],
                      ),
                  ]),
                ),
              )),
            ]);
          },
        ),
        const SizedBox(height: 16),
        const Text(
            'Actuals count only categorised debit transactions matched to an '
            'imported statement. Pending transactions and unclassified spending '
            'are excluded from category alerts. A statement match is not independent '
            'bank/provider settlement confirmation.',
            style: TextStyle(fontSize: 12)),
      ]),
    ),
  );
}
