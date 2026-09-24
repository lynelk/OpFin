import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/services/financial_spaces_api.dart';
import 'package:opfin/services/financial_wellbeing_api.dart';
import 'package:opfin/services/personal_home_api.dart';

class PersonalMoneyScreen extends StatefulWidget {
  const PersonalMoneyScreen({super.key});

  @override
  State<PersonalMoneyScreen> createState() => _PersonalMoneyScreenState();
}

class _PersonalMoneyScreenState extends State<PersonalMoneyScreen> {
  late Future<Map<String, dynamic>> _state;
  final NumberFormat _money = NumberFormat('#,##0', 'en_US');

  @override
  void initState() {
    super.initState();
    _state = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final results = await Future.wait([
      PersonalHomeApi.load(),
      FinancialWellbeingApi.accounts(),
    ]);
    final home = (results[0] as Map).cast<String, dynamic>();
    final accounts = results[1] as List<Map<String, dynamic>>;
    final spaces = (home['spaces'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
    Map<String, dynamic>? personal;
    for (final space in spaces) {
      if (space['type'] == 'personal') {
        personal = space;
        break;
      }
    }
    final obligations = personal == null
        ? <Map<String, dynamic>>[]
        : await FinancialSpacesApi.obligations((personal['id'] as num).toInt());

    return {
      ...home,
      'accounts': accounts,
      'personal_space': personal,
      'obligations': obligations,
    };
  }

  Future<void> _refresh() async {
    setState(() => _state = _load());
    await _state;
  }

  int _n(dynamic value) =>
      value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

  String _amount(dynamic value, String currency) =>
      currency + ' ' + _money.format(_n(value));

  Future<void> _addBalance(String currency) async {
    String type = 'mobile_money';
    final name = TextEditingController();
    final balance = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setLocal) => AlertDialog(
          title: const Text('Add money account'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                decoration: const InputDecoration(
                  labelText: 'Account name',
                  hintText: 'e.g. Main mobile money',
                ),
              ),
              DropdownButtonFormField<String>(
                initialValue: type,
                decoration: const InputDecoration(labelText: 'Account type'),
                items: const [
                  DropdownMenuItem(value: 'mobile_money', child: Text('Mobile money')),
                  DropdownMenuItem(value: 'bank', child: Text('Bank')),
                  DropdownMenuItem(value: 'cash', child: Text('Cash')),
                  DropdownMenuItem(value: 'other', child: Text('Other')),
                ],
                onChanged: (value) => setLocal(() => type = value ?? type),
              ),
              TextField(
                controller: balance,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(labelText: 'Current balance (' + currency + ')'),
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
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );

    final amount = int.tryParse(balance.text.replaceAll(',', '').trim()) ?? -1;
    if (ok != true || name.text.trim().isEmpty || amount < 0) return;

    try {
      await FinancialWellbeingApi.addAccount(
        displayName: name.text.trim(),
        accountType: type,
        balanceMinor: amount,
        currency: currency,
      );
      if (!mounted) return;
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  Future<void> _addDebt(Map<String, dynamic> personal, String currency) async {
    final person = TextEditingController();
    final amount = TextEditingController();
    DateTime? dueDate;

    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setLocal) => AlertDialog(
          title: const Text('Add a debt'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: person,
                  decoration: const InputDecoration(
                    labelText: 'Who or what do you owe?',
                    hintText: 'e.g. School fees, shop credit, family loan',
                  ),
                ),
                TextField(
                  controller: amount,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(labelText: 'Amount owed (' + currency + ')'),
                ),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Due date'),
                  subtitle: Text(
                    dueDate == null
                        ? 'Optional'
                        : DateFormat('d MMM y').format(dueDate!),
                  ),
                  trailing: const Icon(Icons.calendar_today_outlined),
                  onTap: () async {
                    final picked = await showDatePicker(
                      context: dialogContext,
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 3650)),
                      initialDate: dueDate ?? DateTime.now().add(const Duration(days: 30)),
                    );
                    if (picked != null) setLocal(() => dueDate = picked);
                  },
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Save debt'),
            ),
          ],
        ),
      ),
    );

    final debtAmount = int.tryParse(amount.text.replaceAll(',', '').trim()) ?? 0;
    if (ok != true || person.text.trim().isEmpty || debtAmount <= 0) return;

    try {
      await FinancialSpacesApi.addObligation(
        (personal['id'] as num).toInt(),
        'personal_debt',
        'i_owe',
        person.text.trim(),
        debtAmount,
        dueDate: dueDate == null ? null : DateFormat('yyyy-MM-dd').format(dueDate!),
        currency: currency,
      );
      if (!mounted) return;
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  Future<void> _settleDebt(
    Map<String, dynamic> personal,
    Map<String, dynamic> debt,
    String currency,
  ) async {
    final outstanding = _n(debt['outstanding_amount_minor']);
    if (outstanding <= 0) return;

    final amount = TextEditingController(text: outstanding.toString());
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Record debt payment'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Outstanding: ' + _amount(outstanding, currency),
            ),
            TextField(
              controller: amount,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: 'Amount paid (' + currency + ')'),
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
            child: const Text('Record payment'),
          ),
        ],
      ),
    );

    final paid = int.tryParse(amount.text.replaceAll(',', '').trim()) ?? 0;
    if (ok != true || paid <= 0) return;

    try {
      await FinancialSpacesApi.settleObligation(
        (personal['id'] as num).toInt(),
        _n(debt['id']),
        paid,
      );
      if (!mounted) return;
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('My money')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _state,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(snapshot.error.toString(), textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _refresh, child: const Text('Try again')),
                    ],
                  ),
                ),
              );
            }

            final data = snapshot.data ?? const <String, dynamic>{};
            final compass = (data['compass'] as Map?)?.cast<String, dynamic>() ??
                const <String, dynamic>{};
            final position = (compass['position'] as Map?)?.cast<String, dynamic>() ??
                const <String, dynamic>{};
            final cashFlow = (compass['cash_flow'] as Map?)?.cast<String, dynamic>() ??
                const <String, dynamic>{};
            final calendar = (compass['calendar'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .toList();
            final accounts = (data['accounts'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .toList();
            final obligations = (data['obligations'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .where((item) => item['direction'] == 'i_owe' && item['status'] == 'open')
                .toList();
            final personal = (data['personal_space'] as Map?)?.cast<String, dynamic>();
            final currency = compass['currency']?.toString() ?? 'UGX';
            final safe = position['safe_to_spend_minor'];

            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text(
                    'Know where you stand',
                    style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Record the money and debts that matter to your day-to-day plan. OpFin keeps provider-confirmed records separate from what you enter yourself.',
                  ),
                  const SizedBox(height: 18),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(safe == null ? 'Available money' : 'Safe to spend'),
                          const SizedBox(height: 4),
                          Text(
                            safe == null
                                ? (position['available_money_minor'] == null
                                    ? 'Add your balances'
                                    : _amount(position['available_money_minor'], currency))
                                : _amount(safe, currency),
                            style: const TextStyle(
                              fontSize: 30,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Text(
                            'Debt: ' +
                                _amount(position['debt_obligations_minor'], currency) +
                                ' · Savings: ' +
                                _amount(position['current_savings_minor'], currency),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: () => _addBalance(currency),
                          icon: const Icon(Icons.add_card_outlined),
                          label: const Text('Add balance'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed:
                              personal == null ? null : () => _addDebt(personal, currency),
                          icon: const Icon(Icons.receipt_long_outlined),
                          label: const Text('Add debt'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'My balances',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  if (accounts.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'No current cash, mobile-money or bank balance has been recorded.',
                        ),
                      ),
                    )
                  else
                    ...accounts.map(
                      (account) => Card(
                        child: ListTile(
                          leading: const Icon(Icons.account_balance_wallet_outlined),
                          title: Text(account['display_name']?.toString() ?? 'Money account'),
                          subtitle: Text(
                            (account['account_type']?.toString() ?? 'account')
                                .replaceAll('_', ' '),
                          ),
                          trailing: Text(
                            _amount(
                              account['balance_minor'],
                              account['currency']?.toString() ?? currency,
                            ),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 16),
                  const Text(
                    'Debts I am planning',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  if (obligations.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'No additional personal debt has been recorded. OpFin loans are added to your Financial Compass automatically.',
                        ),
                      ),
                    )
                  else
                    ...obligations.map(
                      (debt) => Card(
                        child: ListTile(
                          leading: const Icon(Icons.receipt_long_outlined),
                          title: Text(debt['counterparty_name']?.toString() ?? 'Debt'),
                          subtitle: Text(
                            debt['due_date'] == null
                                ? 'No due date recorded'
                                : 'Due ' + debt['due_date'].toString(),
                          ),
                          trailing: TextButton(
                            onPressed: personal == null
                                ? null
                                : () => _settleDebt(personal, debt, currency),
                            child: Text(_amount(debt['outstanding_amount_minor'], currency)),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 16),
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.swap_vert),
                      title: const Text('This month'),
                      subtitle: Text(
                        'Income ' +
                            _amount(cashFlow['income_minor'], currency) +
                            ' · Spending ' +
                            _amount(cashFlow['expense_minor'], currency),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Coming up',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  if (calendar.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('No confirmed or scheduled item is recorded for the next 30 days.'),
                      ),
                    )
                  else
                    ...calendar.take(6).map(
                      (event) => Card(
                        child: ListTile(
                          leading: Icon(
                            event['direction'] == 'income'
                                ? Icons.south_west
                                : Icons.north_east,
                          ),
                          title: Text(event['title']?.toString() ?? 'Upcoming item'),
                          subtitle: Text(
                            (event['scheduled_for']?.toString() ?? '').split('T').first,
                          ),
                          trailing: Text(
                            _amount(event['amount_minor'], event['currency']?.toString() ?? currency),
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
