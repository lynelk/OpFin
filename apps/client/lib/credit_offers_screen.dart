import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/services/credit_profile_api.dart';
import 'package:opfin/services/user_session.dart';

class CreditOffersScreen extends StatefulWidget {
  const CreditOffersScreen({super.key});

  @override
  State<CreditOffersScreen> createState() => _CreditOffersScreenState();
}

class _CreditOffersScreenState extends State<CreditOffersScreen> {
  late Future<List<Map<String, dynamic>>> _offers;
  final _money = NumberFormat('#,##0', 'en_US');

  @override
  void initState() {
    super.initState();
    _offers = _load();
  }

  Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Secure session is required.');
    return {'Authorization': 'Bearer $token', 'Accept': 'application/json', 'Content-Type': 'application/json'};
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await http.get(Uri.parse('$apiUrl/credit/offers'), headers: await _headers());
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode != 200 || decoded['success'] != true) throw Exception(decoded['message'] ?? 'Unable to load offers.');
    final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    return (data['offers'] as List? ?? const []).whereType<Map>().map((e) => e.cast<String, dynamic>()).toList();
  }

  int _amount(dynamic value) => value is num ? value.toInt() : int.tryParse('$value') ?? 0;
  String _ugx(dynamic value) => 'UGX ${_money.format(_amount(value))}';

  Future<void> _review(Map<String, dynamic> summary) async {
    final id = _amount(summary['id']);
    final response = await http.get(Uri.parse('$apiUrl/credit/offers/$id'), headers: await _headers());
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode != 200 || decoded['success'] != true) {
      _message(decoded['message']?.toString() ?? 'Unable to load offer.');
      return;
    }
    final data = (decoded['data'] as Map).cast<String, dynamic>();
    if (!mounted) return;
    await Navigator.push(context, MaterialPageRoute(builder: (_) => _CreditOfferDetail(offer: (data['offer'] as Map).cast<String, dynamic>(), disclosureHash: data['disclosure_hash'].toString())));
    setState(() => _offers = _load());
  }

  void _message(String message) {
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Credit offers')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _offers,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
          if (snapshot.hasError) return Center(child: Text(snapshot.error.toString()));
          final offers = snapshot.data ?? const [];
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              const Text('Review every cost before accepting', style: TextStyle(fontSize: 23, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text('Mobile-store personal-loan applications cannot require full repayment in 60 days or less. Standard OpFin mobile terms begin at 90 days where an eligible product is available. Every offer shows its equivalent APR, fees and final repayment date.'),
              const SizedBox(height: 18),
              if (offers.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(16), child: Text('No credit offers are ready yet.'))),
              ...offers.map((offer) => Card(
                    child: ListTile(
                      title: Text(_ugx(offer['net_disbursement_minor']), style: const TextStyle(fontWeight: FontWeight.bold)),
                      subtitle: Text('Repay ${_ugx(offer['total_repayment_minor'])} · ${offer['duration_days']} days · ${offer['status']}'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => _review(offer),
                    ),
                  )),
            ],
          );
        },
      ),
    );
  }
}

class _CreditOfferDetail extends StatefulWidget {
  const _CreditOfferDetail({required this.offer, required this.disclosureHash});
  final Map<String, dynamic> offer;
  final String disclosureHash;

  @override
  State<_CreditOfferDetail> createState() => _CreditOfferDetailState();
}

class _CreditOfferDetailState extends State<_CreditOfferDetail> {
  bool _accepted = false;
  bool _submitting = false;
  final _money = NumberFormat('#,##0', 'en_US');
  late Future<List<Map<String, dynamic>>> _wallets;
  int? _walletId;

  @override
  void initState() {
    super.initState();
    _wallets = CreditProfileApi.wallets().then((items) {
      if (items.isNotEmpty && _walletId == null) {
        final defaults = items
            .where((item) => item['is_default_disbursement'] == true)
            .toList();
        _walletId = _amount(
            (defaults.isNotEmpty ? defaults.first : items.first)['id']);
      }
      return items;
    });
  }

  int _amount(dynamic value) => value is num ? value.toInt() : int.tryParse('$value') ?? 0;
  String _ugx(dynamic value) => 'UGX ${_money.format(_amount(value))}';

  Map<String, dynamic> get _disclosure {
    final raw = widget.offer['disclosure_snapshot'];
    return raw is Map ? raw.cast<String, dynamic>() : <String, dynamic>{};
  }

  Future<void> _accept() async {
    if (!_accepted) return;
    setState(() => _submitting = true);
    try {
      final token = await UserSession.getAccessToken();
      final response = await http.post(
        Uri.parse('$apiUrl/credit/offers/${widget.offer['id']}/accept'),
        headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json', 'Content-Type': 'application/json'},
        body: jsonEncode({'accept_disclosures': true, 'disclosure_hash': widget.disclosureHash, 'wallet_id': _walletId}),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode < 200 || response.statusCode >= 300 || decoded['success'] != true) throw Exception(decoded['message'] ?? 'Unable to accept offer.');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Offer accepted. Disbursement remains pending until provider success is confirmed.')));
      Navigator.pop(context);
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _row(String label, String value) => ListTile(contentPadding: EdgeInsets.zero, title: Text(label), trailing: Flexible(child: Text(value, textAlign: TextAlign.end, style: const TextStyle(fontWeight: FontWeight.bold))));

  @override
  Widget build(BuildContext context) {
    final offer = widget.offer;
    final apr = _disclosure['equivalent_maximum_apr_percent'];
    final firstDue = _disclosure['first_payment_due_days_after_disbursement'];
    final finalDue = _disclosure['full_repayment_due_days_after_disbursement'] ??
        _disclosure['final_payment_due_days_after_disbursement'];
    final feeBreakdown = (_disclosure['fee_breakdown'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    final defaultTerms = (_disclosure['default_and_penalty_terms'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    final complaints = (_disclosure['complaints_procedure'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    final provider = (_disclosure['regulated_provider'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    return Scaffold(
      appBar: AppBar(title: const Text('Review credit offer')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        _row('Amount you receive', _ugx(offer['net_disbursement_minor'])),
        _row('Interest', _ugx(offer['interest_amount_minor'])),
        _row('Fees', _ugx(offer['fees_minor'])),
        if (feeBreakdown.isNotEmpty) ...[
          _row('Access fee', _ugx(feeBreakdown['access_fee_minor'])),
          _row('Disbursement fee', _ugx(feeBreakdown['disbursement_fee_minor'])),
          _row('How fees are applied', feeBreakdown['treatment']?.toString() ?? ''),
        ],
        _row('Interest rate', '${offer['interest_rate_percent']}% per ${offer['interest_cycle']}'),
        _row('Interest method', offer['interest_type']?.toString() ?? ''),
        if (_disclosure['interest_calculation'] != null)
          _row('How interest is calculated', _disclosure['interest_calculation'].toString()),
        if (_disclosure['total_cost_of_credit_minor'] != null)
          _row('Total cost of credit', _ugx(_disclosure['total_cost_of_credit_minor'])),
        _row('Total repayment', _ugx(offer['total_repayment_minor'])),
        _row('Duration', '${offer['duration_days']} days'),
        _row('Repayment frequency', offer['repayment_frequency']?.toString() ?? ''),
        if (apr != null) _row('Equivalent maximum APR', '$apr% including fees'),
        if (firstDue != null) _row('First payment due', '$firstDue days after successful disbursement'),
        if (finalDue != null) _row('Full repayment due', '$finalDue days after successful disbursement'),
        if (defaultTerms.isNotEmpty) ...[
          const SizedBox(height: 12),
          const Text('If you do not pay on time', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 6),
          Text(
            'Default interest: ${defaultTerms['default_interest_rate_percent'] ?? 0}% per ${defaultTerms['default_interest_cycle'] ?? 'month'}. '
            '${defaultTerms['cap_basis'] ?? 'Regulatory recovery limits apply.'}',
          ),
          if (defaultTerms['default_interest_cap_minor'] != null)
            _row('Maximum default-interest penalty', _ugx(defaultTerms['default_interest_cap_minor'])),
        ],
        if (provider.isNotEmpty) ...[
          const SizedBox(height: 16),
          const Text('Who provides this credit?', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 6),
          Text(provider['licensed_entity_name']?.toString() ?? provider['trading_name']?.toString() ?? 'OpFin'),
          if (provider['umra_license_number'] != null)
            Text('UMRA licence: ${provider['umra_license_number']}'),
          if (provider['business_address'] != null)
            Text(provider['business_address'].toString()),
        ],
        if (complaints.isNotEmpty) ...[
          const SizedBox(height: 16),
          const Text('Complaints and help', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 6),
          Text('We aim to resolve complaints within ${complaints['resolution_target_days'] ?? 30} days.'),
          if (complaints['email'] != null) Text('Email: ${complaints['email']}'),
          if (complaints['phone'] != null) Text('Phone: ${complaints['phone']}'),
          if (complaints['url'] != null) Text('Online: ${complaints['url']}'),
        ],
        const SizedBox(height: 12),
        FutureBuilder<List<Map<String, dynamic>>>(
          future: _wallets,
          builder: (context, snapshot) {
            final wallets = snapshot.data ?? const <Map<String, dynamic>>[];
            if (snapshot.connectionState != ConnectionState.done) {
              return const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: LinearProgressIndicator(),
              );
            }
            if (wallets.isEmpty) {
              return const Card(
                child: Padding(
                  padding: EdgeInsets.all(14),
                  child: Text('Add and verify a wallet before accepting this offer.'),
                ),
              );
            }
            return DropdownButtonFormField<int>(
              key: ValueKey(_walletId),
              initialValue: _walletId,
              decoration: const InputDecoration(
                labelText: 'Receive money on',
                border: OutlineInputBorder(),
              ),
              items: wallets.map((wallet) {
                final msisdn = wallet['msisdn']?.toString() ?? '';
                final masked = msisdn.length > 4
                    ? '•••• ${msisdn.substring(msisdn.length - 4)}'
                    : msisdn;
                return DropdownMenuItem(
                  value: _amount(wallet['id']),
                  child: Text('${wallet['provider'] ?? 'Mobile money'} · $masked'),
                );
              }).toList(),
              onChanged: _submitting
                  ? null
                  : (value) => setState(() => _walletId = value),
            );
          },
        ),
        const SizedBox(height: 12),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          value: _accepted,
          onChanged: (value) => setState(() => _accepted = value == true),
          title: const Text('I have reviewed and accept the amount received, interest calculation, fees and when they apply, default terms, total cost, repayment dates and complaint procedure.'),
        ),
        FilledButton(onPressed: !_accepted || _walletId == null || _submitting || offer['status'] != 'offered' ? null : _accept, child: Text(_submitting ? 'Submitting…' : 'Accept offer and request disbursement')),
      ]),
    );
  }
}
