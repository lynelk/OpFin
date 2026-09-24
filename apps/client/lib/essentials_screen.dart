import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/services/essentials_api.dart';

class EssentialsScreen extends StatefulWidget {
  const EssentialsScreen({super.key});

  @override
  State<EssentialsScreen> createState() => _EssentialsScreenState();
}

class _EssentialsScreenState extends State<EssentialsScreen> {
  late Future<Map<String, dynamic>> _state;
  final _money = NumberFormat('#,##0', 'en_US');

  @override
  void initState() {
    super.initState();
    _state = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final values = await Future.wait([
      EssentialsApi.summary(),
      EssentialsApi.catalogue(),
      EssentialsApi.partnerAuthorisations(),
    ]);
    return {
      'summary': values[0],
      'catalogue': values[1],
      'authorisations': values[2],
    };
  }

  int _n(dynamic value) => value is num ? value.toInt() : int.tryParse('$value') ?? 0;
  String _ugx(dynamic value) => 'UGX ${_money.format(_n(value))}';

  Future<void> _refresh() async {
    setState(() => _state = _load());
    await _state;
  }

  void _message(String value) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }

  Future<void> _refreshEligibility() async {
    try {
      await EssentialsApi.refreshEligibility();
      _message('Participating lender eligibility refreshed.');
      await _refresh();
    } catch (e) {
      _message(e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _addAccount(List<Map<String, dynamic>> billers) async {
    if (billers.isEmpty) {
      _message('No Essentials service providers are available right now.');
      return;
    }

    int billerId = _n(billers.first['id']);
    final reference = TextEditingController();
    final nickname = TextEditingController();
    final landlord = TextEditingController();
    final beneficiary = TextEditingController();
    final channel = TextEditingController(text: 'mobile_money');

    final created = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          final selected = billers.firstWhere(
            (b) => _n(b['id']) == billerId,
            orElse: () => billers.first,
          );
          final rent = selected['category']?.toString() == 'rent';
          return AlertDialog(
            title: const Text('Add an essential service'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<int>(
                    value: billerId,
                    decoration: const InputDecoration(labelText: 'Service'),
                    items: billers
                        .map((b) => DropdownMenuItem(
                              value: _n(b['id']),
                              child: Text(b['name']?.toString() ?? 'Service'),
                            ))
                        .toList(),
                    onChanged: (value) => setDialogState(() => billerId = value ?? billerId),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: reference,
                    decoration: InputDecoration(
                      labelText: selected['account_label']?.toString() ?? 'Account reference',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: nickname,
                    decoration: const InputDecoration(labelText: 'Nickname (optional)'),
                  ),
                  if (rent) ...[
                    const SizedBox(height: 12),
                    TextField(
                      controller: landlord,
                      decoration: const InputDecoration(labelText: 'Landlord / property manager'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: beneficiary,
                      decoration: const InputDecoration(labelText: 'Payment beneficiary name'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: channel,
                      decoration: const InputDecoration(labelText: 'Beneficiary payment channel'),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Rental beneficiaries are verified before financing can be offered.',
                      style: TextStyle(color: OpFinColors.muted),
                    ),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
              FilledButton(
                onPressed: () async {
                  if (reference.text.trim().isEmpty) return;
                  try {
                    await EssentialsApi.addAccount(
                      billerId: billerId,
                      accountReference: reference.text.trim(),
                      nickname: nickname.text.trim(),
                      metadata: rent
                          ? {
                              'landlord_name': landlord.text.trim(),
                              'beneficiary_name': beneficiary.text.trim(),
                              'beneficiary_channel': channel.text.trim(),
                            }
                          : null,
                    );
                    if (dialogContext.mounted) Navigator.pop(dialogContext, true);
                  } catch (e) {
                    if (!dialogContext.mounted) return;
                    ScaffoldMessenger.of(dialogContext).showSnackBar(
                      SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
                    );
                  }
                },
                child: const Text('Save'),
              ),
            ],
          );
        },
      ),
    );

    if (created == true) {
      _message('Service account saved.');
      await _refresh();
    }
  }

  Future<void> _verifyAccount(Map<String, dynamic> account) async {
    try {
      final data = await EssentialsApi.verifyAccount(_n(account['id']));
      final updated = (data['account'] as Map?)?.cast<String, dynamic>() ?? {};
      final status = updated['verification_status']?.toString() ?? 'updated';
      _message(status == 'pending_manual_review'
          ? 'This account is waiting for manual beneficiary verification.'
          : 'Verification status: $status');
      await _refresh();
    } catch (e) {
      _message(e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _finance(Map<String, dynamic> account) async {
    final amount = TextEditingController();
    final requested = await showDialog<int>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Finance this essential'),
        content: TextField(
          controller: amount,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Amount (UGX)'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
          FilledButton(
            onPressed: () {
              final value = int.tryParse(amount.text.replaceAll(',', '').trim());
              if (value != null && value > 0) Navigator.pop(dialogContext, value);
            },
            child: const Text('Check offers'),
          ),
        ],
      ),
    );
    if (requested == null) return;

    try {
      final data = await EssentialsApi.createQuote(
        accountId: _n(account['id']),
        amountMinor: requested,
      );
      final quote = (data['quote'] as Map?)?.cast<String, dynamic>() ?? {};
      final hash = data['disclosure_hash']?.toString() ?? '';
      if (!mounted) return;
      await _showQuote(quote, hash);
    } catch (e) {
      _message(e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _showQuote(Map<String, dynamic> quote, String hash) async {
    final disclosure = (quote['disclosure_snapshot'] as Map?)?.cast<String, dynamic>() ?? {};
    final lender = (disclosure['lender'] as Map?)?.cast<String, dynamic>() ?? {};
    var accepted = false;

    final result = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Review your Essentials offer'),
          content: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Lender: ${lender['name'] ?? 'Third-party lender'}',
                    style: const TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 12),
                _QuoteRow('Amount paid to service provider', _ugx(quote['amount_minor'])),
                _QuoteRow('Interest', _ugx(quote['interest_minor'])),
                _QuoteRow('Fees', _ugx(quote['fees_minor'])),
                _QuoteRow('Total repayment', _ugx(quote['total_repayment_minor'])),
                _QuoteRow('Term', '${quote['term_days'] ?? ''} days'),
                const Divider(height: 28),
                const Text(
                  'The money is paid directly to the verified service provider or beneficiary. OpFin arranges and services the financing; the named third party is the lender.',
                ),
                const SizedBox(height: 10),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: accepted,
                  onChanged: (value) => setDialogState(() => accepted = value ?? false),
                  title: const Text('I have reviewed the lender, cost and repayment terms.'),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Not now')),
            FilledButton(
              onPressed: accepted && hash.length == 64
                  ? () => Navigator.pop(dialogContext, true)
                  : null,
              child: const Text('Accept & pay provider'),
            ),
          ],
        ),
      ),
    );

    if (result != true) return;
    try {
      await EssentialsApi.acceptQuote(quoteId: _n(quote['id']), disclosureHash: hash);
      _message('Provider payment initiated. The confirmed status will appear in Essentials.');
      await _refresh();
    } catch (e) {
      _message(e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _repay(Map<String, dynamic> advance) async {
    final max = _n(advance['outstanding_minor']);
    final amount = TextEditingController(text: max.toString());
    final chosen = await showDialog<int>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Repay Essentials financing'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Outstanding: ${_ugx(max)}'),
            const SizedBox(height: 12),
            TextField(
              controller: amount,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Repayment amount (UGX)'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
          FilledButton(
            onPressed: () {
              final value = int.tryParse(amount.text.replaceAll(',', '').trim());
              if (value != null && value > 0 && value <= max) Navigator.pop(dialogContext, value);
            },
            child: const Text('Continue'),
          ),
        ],
      ),
    );
    if (chosen == null) return;

    try {
      await EssentialsApi.repay(advanceId: _n(advance['id']), amountMinor: chosen);
      _message('Repayment submitted for provider confirmation.');
      await _refresh();
    } catch (e) {
      _message(e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _grantPartner(List<Map<String, dynamic>> platforms) async {
    if (platforms.isEmpty) {
      _message('No approved external platform is available for Essentials access.');
      return;
    }
    int selected = _n(platforms.first['id']);
    final granted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Connect an approved platform'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                value: selected,
                decoration: const InputDecoration(labelText: 'Platform'),
                items: platforms
                    .map((platform) => DropdownMenuItem(
                          value: _n(platform['id']),
                          child: Text(platform['name']?.toString() ?? 'Approved platform'),
                        ))
                    .toList(),
                onChanged: (value) => setDialogState(() => selected = value ?? selected),
              ),
              const SizedBox(height: 12),
              const Text(
                'For 30 days this platform may check Essentials eligibility, save a service account, create a quote and read status. It still cannot create debt without your separate confirmation.',
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
            FilledButton(
              onPressed: () async {
                try {
                  await EssentialsApi.authorisePartnerPlatform(partnerAccountId: selected);
                  if (dialogContext.mounted) Navigator.pop(dialogContext, true);
                } catch (e) {
                  if (!dialogContext.mounted) return;
                  ScaffoldMessenger.of(dialogContext).showSnackBar(
                    SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
                  );
                }
              },
              child: const Text('Grant access'),
            ),
          ],
        ),
      ),
    );
    if (granted == true) {
      _message('Platform access granted for 30 days.');
      await _refresh();
    }
  }

  Future<void> _revokePartner(int id) async {
    try {
      await EssentialsApi.revokePartnerAuthorisation(id);
      _message('Platform access revoked.');
      await _refresh();
    } catch (e) {
      _message(e.toString().replaceFirst('Exception: ', ''));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('OpFin Essentials')),
        body: RefreshIndicator(
          onRefresh: _refresh,
          child: FutureBuilder<Map<String, dynamic>>(
            future: _state,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return ListView(children: const [
                  SizedBox(height: 260),
                  Center(child: CircularProgressIndicator()),
                ]);
              }
              if (snapshot.hasError) {
                return ListView(padding: const EdgeInsets.all(24), children: [
                  const SizedBox(height: 100),
                  const Icon(Icons.cloud_off_outlined, size: 48),
                  const SizedBox(height: 12),
                  Text(snapshot.error.toString(), textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: _refresh, child: const Text('Try again')),
                ]);
              }

              final root = snapshot.data ?? {};
              final summary = (root['summary'] as Map?)?.cast<String, dynamic>() ?? {};
              final catalogue = (root['catalogue'] as Map?)?.cast<String, dynamic>() ?? {};
              final billers = (catalogue['billers'] as List? ?? const [])
                  .whereType<Map>()
                  .map((e) => e.cast<String, dynamic>())
                  .toList();
              final platforms = (catalogue['platforms'] as List? ?? const [])
                  .whereType<Map>()
                  .map((e) => e.cast<String, dynamic>())
                  .toList();
              final accounts = (summary['accounts'] as List? ?? const [])
                  .whereType<Map>()
                  .map((e) => e.cast<String, dynamic>())
                  .toList();
              final advances = (summary['advances'] as List? ?? const [])
                  .whereType<Map>()
                  .map((e) => e.cast<String, dynamic>())
                  .toList();
              final auths = (root['authorisations'] as List? ?? const [])
                  .whereType<Map>()
                  .map((e) => e.cast<String, dynamic>())
                  .toList();

              return ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text('Keep essentials running',
                      style: TextStyle(fontSize: 27, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 6),
                  const Text(
                    'Finance verified household or business essentials through approved third-party lenders. OpFin keeps the bill, lender and repayment in one financial picture.',
                    style: TextStyle(color: OpFinColors.muted),
                  ),
                  const SizedBox(height: 18),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('Available Essentials headroom',
                            style: TextStyle(color: OpFinColors.muted)),
                        Text(_ugx(summary['overall_available_limit_minor']),
                            style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w800)),
                        const SizedBox(height: 6),
                        Text('Outstanding: ${_ugx(summary['outstanding_minor'])}'),
                        const SizedBox(height: 14),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton.icon(
                            onPressed: _refreshEligibility,
                            icon: const Icon(Icons.refresh),
                            label: const Text('Check participating lenders'),
                          ),
                        ),
                      ]),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Row(children: [
                    const Expanded(
                      child: Text('My essentials',
                          style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                    ),
                    TextButton.icon(
                      onPressed: () => _addAccount(billers),
                      icon: const Icon(Icons.add),
                      label: const Text('Add'),
                    ),
                  ]),
                  if (accounts.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('Add electricity, water, internet, TV, LPG or rent to finance a verified essential when you need it.'),
                      ),
                    )
                  else
                    ...accounts.map((account) {
                      final biller = (account['biller'] as Map?)?.cast<String, dynamic>() ?? {};
                      final status = account['verification_status']?.toString() ?? 'pending';
                      return Card(
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Row(children: [
                              Expanded(
                                child: Text(
                                  account['nickname']?.toString().trim().isNotEmpty == true
                                      ? account['nickname'].toString()
                                      : biller['name']?.toString() ?? 'Essential service',
                                  style: const TextStyle(fontWeight: FontWeight.w800),
                                ),
                              ),
                              Text(status.replaceAll('_', ' ')),
                            ]),
                            const SizedBox(height: 4),
                            Text('${biller['category'] ?? ''} · •••• ${account['reference_last4'] ?? ''}'),
                            const SizedBox(height: 12),
                            Wrap(spacing: 8, runSpacing: 8, children: [
                              if (status != 'verified')
                                OutlinedButton(
                                  onPressed: status == 'pending_manual_review'
                                      ? null
                                      : () => _verifyAccount(account),
                                  child: Text(status == 'pending_manual_review' ? 'Under review' : 'Verify'),
                                ),
                              FilledButton(
                                onPressed: status == 'verified' ? () => _finance(account) : null,
                                child: const Text('Finance'),
                              ),
                            ]),
                          ]),
                        ),
                      );
                    }),
                  const SizedBox(height: 18),
                  const Text('Financing activity',
                      style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 8),
                  if (advances.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('No Essentials financing yet. Ordinary OpFin borrowing remains available separately.'),
                      ),
                    )
                  else
                    ...advances.map((advance) => Card(
                          child: ListTile(
                            title: Text(
                              '${advance['status']?.toString().replaceAll('_', ' ') ?? 'Essentials financing'}',
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                            subtitle: Text(
                              'Outstanding ${_ugx(advance['outstanding_minor'])}'
                              '${advance['final_due_date'] != null ? ' · Final due ${advance['final_due_date']}' : ''}',
                            ),
                            trailing: ['active', 'overdue'].contains(advance['status'])
                                ? TextButton(
                                    onPressed: () => _repay(advance),
                                    child: const Text('Repay'),
                                  )
                                : null,
                          ),
                        )),
                  const SizedBox(height: 18),
                  Row(children: [
                    const Expanded(
                      child: Text('Connected platforms',
                          style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                    ),
                    if (platforms.isNotEmpty)
                      TextButton.icon(
                        onPressed: () => _grantPartner(platforms),
                        icon: const Icon(Icons.link),
                        label: const Text('Connect'),
                      ),
                  ]),
                  const SizedBox(height: 8),
                  if (auths.isEmpty)
                    const Text(
                      'No external platform currently has permission to use your Essentials profile.',
                      style: TextStyle(color: OpFinColors.muted),
                    )
                  else
                    ...auths.map((auth) => Card(
                          child: ListTile(
                            title: Text(auth['partner_name']?.toString() ?? 'Approved platform'),
                            subtitle: Text(
                              '${(auth['scopes'] as List? ?? const []).join(', ')} · ${auth['status'] ?? ''}',
                            ),
                            trailing: auth['status'] == 'active'
                                ? TextButton(
                                    onPressed: () => _revokePartner(_n(auth['id'])),
                                    child: const Text('Revoke'),
                                  )
                                : null,
                          ),
                        )),
                  const SizedBox(height: 18),
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text(
                        'OpFin does not become the primary lender when you use Essentials. The lender is named in every offer, funds are purpose-bound to the verified provider or beneficiary, and no debt is created without your confirmation.',
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      );
}

class _QuoteRow extends StatelessWidget {
  const _QuoteRow(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(children: [
          Expanded(child: Text(label)),
          const SizedBox(width: 12),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
        ]),
      );
}
