import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/location_context_screen.dart';
import 'package:opfin/services/protection_api.dart';

class ProtectionScreen extends StatefulWidget {
  const ProtectionScreen({super.key});

  @override
  State<ProtectionScreen> createState() => _ProtectionScreenState();
}

class _ProtectionScreenState extends State<ProtectionScreen> {
  late Future<Map<String, dynamic>> _state;
  final NumberFormat _money = NumberFormat('#,##0', 'en_US');
  final Set<int> _premiumInFlight = <int>{};
  final Map<int, String> _premiumIdempotencyKeys = <int, String>{};

  @override
  void initState() {
    super.initState();
    _state = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final results = await Future.wait([
      ProtectionApi.policies(),
      ProtectionApi.products(),
    ]);
    return {
      'policies': results[0],
      'products': results[1],
    };
  }

  Future<void> _refresh() async {
    setState(() => _state = _load());
    await _state;
  }

  int _minor(dynamic value) =>
      value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

  String _amount(dynamic value, [String currency = 'UGX']) =>
      currency + ' ' + _money.format(_minor(value));

  String _status(dynamic value) =>
      (value?.toString() ?? 'unknown').replaceAll('_', ' ');

  Future<void> _enrol(Map<String, dynamic> product) async {
    final insurer = product['insurer_name']?.toString() ?? 'the disclosed insurer';
    final benefits = (product['benefits'] as List? ?? const []).map((e) => e.toString()).toList();
    final exclusions = (product['exclusions'] as List? ?? const []).map((e) => e.toString()).toList();
    final disclosure = (product['disclosure_payload'] as Map?)?.cast<String, dynamic>() ??
        const <String, dynamic>{};
    final termsUrl = product['terms_url']?.toString() ?? '';
    var reviewed = false;

    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setLocal) => AlertDialog(
          title: Text(product['name']?.toString() ?? 'Protection product'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Insurer: ' + insurer),
                const SizedBox(height: 8),
                Text(
                  'Premium: ' +
                      _amount(product['premium_amount_minor'], product['currency']?.toString() ?? 'UGX') +
                      ' · ' +
                      (product['premium_frequency']?.toString() ?? ''),
                ),
                if (benefits.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  const Text('Benefits', style: TextStyle(fontWeight: FontWeight.w700)),
                  ...benefits.map((item) => Text('• ' + item)),
                ],
                const SizedBox(height: 12),
                const Text('Exclusions', style: TextStyle(fontWeight: FontWeight.w700)),
                if (exclusions.isEmpty)
                  const Text('No exclusions are listed in this catalogue response. Review the controlled terms below.')
                else
                  ...exclusions.map((item) => Text('• ' + item)),
                if (disclosure.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  const Text('Controlled disclosure', style: TextStyle(fontWeight: FontWeight.w700)),
                  ...disclosure.entries.map((entry) => Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      entry.key.replaceAll('_', ' ') +
                          ': ' +
                          (entry.value is String
                              ? entry.value.toString()
                              : jsonEncode(entry.value)),
                    ),
                  )),
                ],
                if (termsUrl.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  const Text('Controlled terms', style: TextStyle(fontWeight: FontWeight.w700)),
                  SelectableText(termsUrl),
                ],
                const SizedBox(height: 12),
                Text(
                  insurer +
                      ' issues and manages the cover and claim decisions. Paying through OpFin does not by itself activate cover.',
                ),
                const SizedBox(height: 8),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: reviewed,
                  onChanged: (value) => setLocal(() => reviewed = value == true),
                  title: const Text('I have reviewed the disclosure and controlled terms shown above.'),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Not now'),
            ),
            FilledButton(
              onPressed: reviewed ? () => Navigator.pop(dialogContext, true) : null,
              child: const Text('Accept & continue'),
            ),
          ],
        ),
      ),
    );

    if (accepted != true) return;

    try {
      await ProtectionApi.enroll(
        _minor(product['id']),
        product['disclosure_hash']?.toString() ?? '',
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Enrolment recorded. Cover is not active until the insurer issues the policy.'),
        ),
      );
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  Future<void> _payPremium(Map<String, dynamic> policy) async {
    final policyId = _minor(policy['id']);
    if (_premiumInFlight.contains(policyId)) return;

    final key = _premiumIdempotencyKeys.putIfAbsent(
      policyId,
      () => 'opfin-mobile-premium-' +
          policyId.toString() +
          '-' +
          DateTime.now().microsecondsSinceEpoch.toString(),
    );

    setState(() => _premiumInFlight.add(policyId));
    var acceptedByServer = false;
    try {
      await ProtectionApi.payPremium(policyId, key);
      acceptedByServer = true;
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Premium collection started. The policy remains pending until insurer settlement and issuance are confirmed.',
          ),
        ),
      );
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    } finally {
      if (mounted) {
        setState(() => _premiumInFlight.remove(policyId));
      }
      if (acceptedByServer) {
        _premiumIdempotencyKeys.remove(policyId);
      }
    }
  }

  bool _hasPendingPremium(Map<String, dynamic> policy) {
    final payments = (policy['premium_payments'] as List? ?? const [])
        .whereType<Map>();
    return payments.any((payment) {
      final status = payment['status']?.toString();
      return status == 'collection_pending' ||
          status == 'collected_pending_partner';
    });
  }

  bool _premiumDue(Map<String, dynamic> policy) {
    final status = policy['status']?.toString() ?? '';
    if (status == 'premium_due' || status == 'lapsed') return true;
    if (status != 'active') return false;

    final raw = policy['next_premium_due_date']?.toString();
    if (raw == null || raw.isEmpty) return false;
    final due = DateTime.tryParse(raw);
    if (due == null) return false;
    final today = DateTime.now();
    final todayDate = DateTime(today.year, today.month, today.day);
    final dueDate = DateTime(due.year, due.month, due.day);
    return !dueDate.isAfter(todayDate);
  }

  Future<void> _submitClaim(Map<String, dynamic> policy) async {
    final category = TextEditingController(text: 'other');
    final description = TextEditingController();
    final amount = TextEditingController();
    DateTime incident = DateTime.now();

    final submit = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setLocal) => AlertDialog(
          title: const Text('Submit a claim'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Incident date'),
                  subtitle: Text(DateFormat('d MMM y').format(incident)),
                  trailing: const Icon(Icons.calendar_today_outlined),
                  onTap: () async {
                    final picked = await showDatePicker(
                      context: dialogContext,
                      firstDate: DateTime(2000),
                      lastDate: DateTime.now(),
                      initialDate: incident,
                    );
                    if (picked != null) setLocal(() => incident = picked);
                  },
                ),
                TextField(
                  controller: category,
                  decoration: const InputDecoration(labelText: 'Claim category'),
                ),
                TextField(
                  controller: description,
                  onChanged: (_) => setLocal(() {}),
                  minLines: 3,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    labelText: 'What happened?',
                    helperText: 'Give the insurer enough detail to review the claim.',
                  ),
                ),
                TextField(
                  controller: amount,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Claimed amount (optional)',
                  ),
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
              onPressed: description.text.trim().length < 10
                  ? null
                  : () => Navigator.pop(dialogContext, true),
              child: const Text('Submit'),
            ),
          ],
        ),
      ),
    );

    if (submit != true) return;

    try {
      final entered = int.tryParse(amount.text.replaceAll(',', '').trim());
      await ProtectionApi.submitClaim(
        _minor(policy['id']),
        incidentDate: DateFormat('yyyy-MM-dd').format(incident),
        category: category.text.trim(),
        description: description.text.trim(),
        claimedAmountMinor: entered,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Claim submitted to the insurer or underwriter for review.'),
        ),
      );
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  Future<void> _disputeClaim(Map<String, dynamic> claim) async {
    final reason = TextEditingController();
    final submit = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Request reconsideration'),
        content: TextField(
          controller: reason,
          minLines: 3,
          maxLines: 5,
          decoration: const InputDecoration(
            labelText: 'Why should the insurer reconsider?',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Send request'),
          ),
        ],
      ),
    );

    if (submit != true || reason.text.trim().length < 10) return;

    try {
      await ProtectionApi.disputeClaim(_minor(claim['id']), reason.text.trim());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Reconsideration request recorded.')),
      );
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
        appBar: AppBar(title: const Text('Protection')),
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
                      FilledButton(
                        onPressed: _refresh,
                        child: const Text('Try again'),
                      ),
                    ],
                  ),
                ),
              );
            }

            final data = snapshot.data ?? const <String, dynamic>{};
            final policies = (data['policies'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .toList();
            final products = (data['products'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .toList();

            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text(
                    'Protect what matters',
                    style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'See your cover, upcoming premiums and claims without confusing premium payment with actual policy issuance.',
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'My protection',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                  if (policies.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('You do not have a protection policy recorded yet.'),
                      ),
                    )
                  else
                    ...policies.map(_policyCard),
                  const SizedBox(height: 20),
                  const Text(
                    'Available protection',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                  if (products.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'No independently approved protection product is currently available for you.',
                        ),
                      ),
                    )
                  else
                    ...products.map(_productCard),
                  const SizedBox(height: 12),
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text(
                        'The disclosed insurer or underwriter owns underwriting, policy issuance and claim decisions. OpFin provides the customer experience, payment orchestration and servicing record.',
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      );

  Widget _policyCard(Map<String, dynamic> policy) {
    final product = (policy['product'] as Map?)?.cast<String, dynamic>() ??
        const <String, dynamic>{};
    final claims = (policy['claims'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
    final status = policy['status']?.toString() ?? '';
    final active = status == 'active';
    final policyId = _minor(policy['id']);
    final pendingPremium = _hasPendingPremium(policy);
    final paying = _premiumInFlight.contains(policyId);
    final canPay = _premiumDue(policy) && !pendingPremium;
    final productType = product['product_type']?.toString() ?? '';
    final riskLocationRelevant = const {
      'asset',
      'device',
      'agriculture',
      'livestock',
      'property',
    }.contains(productType);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.shield_outlined),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    product['name']?.toString() ?? 'Protection policy',
                    style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
                  ),
                ),
                Text(_status(status)),
              ],
            ),
            const SizedBox(height: 8),
            Text('Insurer: ' + (product['insurer_name']?.toString() ?? 'See policy')),
            Text(
              'Premium: ' +
                  _amount(
                    policy['premium_amount_minor'],
                    product['currency']?.toString() ?? 'UGX',
                  ) +
                  ' · ' +
                  (policy['premium_frequency']?.toString() ?? ''),
            ),
            if (policy['external_policy_number'] != null)
              Text('Policy: ' + policy['external_policy_number'].toString()),
            if (policy['next_premium_due_date'] != null)
              Text('Next premium: ' + policy['next_premium_due_date'].toString()),
            const SizedBox(height: 12),
            if (canPay || paying)
              FilledButton.icon(
                onPressed: canPay && !paying ? () => _payPremium(policy) : null,
                icon: const Icon(Icons.payments_outlined),
                label: Text(paying ? 'Starting payment…' : 'Pay premium'),
              ),
            if (pendingPremium)
              const Text(
                'A premium payment is already being confirmed. Another collection cannot be started yet.',
              ),
            if (active)
              OutlinedButton.icon(
                onPressed: () => _submitClaim(policy),
                icon: const Icon(Icons.assignment_outlined),
                label: const Text('Submit claim'),
              ),
            if (riskLocationRelevant)
              OutlinedButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => LocationContextScreen(
                      subjectType: 'protection_policy',
                      subjectId: policyId,
                      purpose: 'insured_risk_location',
                      title: 'Insured risk location',
                      description:
                          'Use a precise location only where the insured asset, farm, livestock operation or property depends on a physical site.',
                      countryCode: product['country_code']?.toString() ?? 'UG',
                      preciseRecommended: true,
                    ),
                  ),
                ),
                icon: const Icon(Icons.place_outlined),
                label: const Text('Risk location'),
              ),
            if (status == 'premium_pending' || status == 'pending_issuance')
              const Text(
                'Payment or issuance is still being confirmed. OpFin will not show this cover as active early.',
              ),
            if (claims.isNotEmpty) ...[
              const Divider(height: 28),
              const Text('Claims', style: TextStyle(fontWeight: FontWeight.w700)),
              ...claims.map(
                (claim) => Column(
                  children: [
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        (claim['category']?.toString() ?? 'Claim') +
                            ' · ' +
                            _status(claim['status']),
                      ),
                      subtitle: Text(
                        claim['decision_reason']?.toString() ??
                            claim['incident_date']?.toString() ??
                            '',
                      ),
                      trailing: claim['status'] == 'declined'
                          ? TextButton(
                              onPressed: () => _disputeClaim(claim),
                              child: const Text('Reconsider'),
                            )
                          : null,
                    ),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton.icon(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => LocationContextScreen(
                              subjectType: 'protection_claim',
                              subjectId: _minor(claim['id']),
                              purpose: 'claim_incident_location',
                              title: 'Claim incident location',
                              description:
                                  'Add an incident location only when it is relevant to the insurer review. This does not change who decides the claim.',
                              countryCode:
                                  product['country_code']?.toString() ?? 'UG',
                              preciseRecommended: true,
                            ),
                          ),
                        ),
                        icon: const Icon(Icons.add_location_alt_outlined),
                        label: const Text('Incident location'),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _productCard(Map<String, dynamic> product) {
    final benefits = (product['benefits'] as List? ?? const []).map((e) => e.toString()).toList();
    return Card(
      child: ExpansionTile(
        leading: const Icon(Icons.health_and_safety_outlined),
        title: Text(
          product['name']?.toString() ?? 'Protection product',
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        subtitle: Text(
          (product['insurer_name']?.toString() ?? 'Insurer') +
              ' · ' +
              _amount(
                product['premium_amount_minor'],
                product['currency']?.toString() ?? 'UGX',
              ) +
              ' ' +
              (product['premium_frequency']?.toString() ?? ''),
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: [
          if (benefits.isNotEmpty)
            Align(
              alignment: Alignment.centerLeft,
              child: Text('Includes: ' + benefits.take(2).join(' · ')),
            ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: () => _enrol(product),
              child: const Text('Review & enrol'),
            ),
          ),
        ],
      ),
    );
  }
}
