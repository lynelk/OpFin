import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class PeerLendingScreen extends StatefulWidget {
  const PeerLendingScreen({super.key});

  @override
  State<PeerLendingScreen> createState() => _PeerLendingScreenState();
}

class _PeerLendingScreenState extends State<PeerLendingScreen> {
  final _currency = NumberFormat('#,##0', 'en_US');
  late Future<Map<String, dynamic>> _data;

  @override
  void initState() {
    super.initState();
    _data = _load();
  }

  Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Secure session is required.');
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }

  Future<Map<String, dynamic>> _get(String path) async {
    final response = await http.get(Uri.parse('$apiUrl$path'), headers: await _headers());
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to load marketplace data.');
    }
    return (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> payload) async {
    final response = await http.post(
      Uri.parse('$apiUrl$path'),
      headers: await _headers(),
      body: jsonEncode(payload),
    );
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to complete that action.');
    }
    return (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  Future<Map<String, dynamic>> _load() async {
    final marketplace = await _get('/long-range/participatory/marketplace');
    final overview = await _get('/long-range/overview');
    return {'listings': marketplace['listings'] ?? <dynamic>[], 'overview': overview};
  }

  Map<String, dynamic> _disclosures(dynamic raw) {
    if (raw is Map<String, dynamic>) return raw;
    if (raw is String && raw.isNotEmpty) {
      final decoded = jsonDecode(raw);
      if (decoded is Map) return decoded.cast<String, dynamic>();
    }
    return <String, dynamic>{};
  }

  int _amount(dynamic value) => value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  String _money(dynamic value) => 'UGX ${_currency.format(_amount(value))}';

  Future<void> _refresh() async {
    setState(() => _data = _load());
    await _data;
  }

  Future<void> _requestFunding() async {
    final amountController = TextEditingController();
    final purposeController = TextEditingController();
    final termController = TextEditingController(text: '90');
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Borrow from investors'),
        content: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(controller: amountController, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'How much do you need? (UGX)')),
            TextField(controller: purposeController, decoration: const InputDecoration(labelText: 'What is it for?')),
            TextField(controller: termController, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'How long do you need? (days)')),
            const SizedBox(height: 12),
            const Text('OpFin handles lender, pricing, risk and settlement disclosures through independent review.'),
          ]),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Submit request')),
        ],
      ),
    );
    if (result != true) return;

    final amount = int.tryParse(amountController.text.trim()) ?? 0;
    final term = int.tryParse(termController.text.trim()) ?? 0;
    final purpose = purposeController.text.trim();
    if (amount < 1000 || term < 1 || purpose.isEmpty) {
      _message('Enter an amount, purpose and timeframe.');
      return;
    }

    try {
      await _post('/long-range/participatory/listings', {
        'target_amount_minor': amount,
        'purpose': purpose,
        'term_days': term,
      });
      _message('Funding request submitted for independent review.');
      await _refresh();
    } catch (error) {
      _message(error.toString());
    }
  }

  Future<void> _invest(Map<String, dynamic> listing) async {
    final remaining = _amount(listing['target_amount_minor']) - _amount(listing['funded_amount_minor']);
    final controller = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Lend to ${listing['purpose'] ?? 'this request'}'),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(labelText: 'Amount (UGX)', helperText: '${_money(remaining)} remaining'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Continue')),
        ],
      ),
    );
    if (confirmed != true) return;
    final amount = int.tryParse(controller.text.trim()) ?? 0;
    if (amount < 1000 || amount > remaining) {
      _message('Enter an amount between UGX 1,000 and ${_currency.format(remaining)}.');
      return;
    }

    try {
      final commitment = await _post('/long-range/participatory/commitments', {
        'listing_id': listing['id'],
        'amount_minor': amount,
      });
      final commitmentId = (commitment['commitment'] as Map)['id'];
      final intent = await _post('/long-range/financial-intents', {
        'source_type': 'participatory_commitment',
        'source_id': commitmentId,
        'amount_minor': amount,
        'idempotency_key': 'p2p-$commitmentId-${DateTime.now().microsecondsSinceEpoch}',
      });
      final intentReference = (intent['financial_intent'] as Map)['reference']?.toString() ?? '';
      if (intentReference.isEmpty) throw Exception('Investment reference was not returned.');
      await _sendOtp();
      if (!mounted) return;
      await _confirmOtp(intentReference, amount);
    } catch (error) {
      _message(error.toString());
    }
  }

  Future<void> _sendOtp() async {
    final phone = await UserSession.getPhone();
    if (phone == null || phone.isEmpty) throw Exception('Your registered phone number is missing.');
    final response = await http.post(Uri.parse('$apiUrl/generate-otp'), body: {'phone': phone});
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to send verification code.');
    }
  }

  Future<void> _confirmOtp(String intentReference, int amount) async {
    final controller = TextEditingController();
    final submit = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Confirm investment'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(_money(amount), style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 8),
          const Text('Enter the six-digit code sent to your registered phone.'),
          TextField(controller: controller, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: 'Verification code')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Confirm investment')),
        ],
      ),
    );
    if (submit != true) return;

    final phone = await UserSession.getPhone();
    if (phone == null || phone.isEmpty) throw Exception('Your registered phone number is missing.');
    final verify = await http.post(Uri.parse('$apiUrl/verify-otp'), body: {'phone': phone, 'otp': controller.text.trim()});
    final verifyDecoded = jsonDecode(verify.body) as Map<String, dynamic>;
    if (verify.statusCode < 200 || verify.statusCode >= 300 || verifyDecoded['success'] != true) {
      throw Exception(verifyDecoded['message']?.toString() ?? 'Verification failed.');
    }
    final token = (verifyDecoded['data'] as Map?)?['verification_token']?.toString() ?? '';
    if (token.isEmpty) throw Exception('Verification token was not returned.');

    await _post('/long-range/financial-intents/$intentReference/confirm', {'verification_token': token});
    _message('Investment submitted. It remains processing until provider finality is confirmed.');
    await _refresh();
  }

  void _message(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message.replaceFirst('Exception: ', ''))));
  }

  Widget _opportunity(Map<String, dynamic> listing) {
    final target = _amount(listing['target_amount_minor']);
    final funded = _amount(listing['funded_amount_minor']);
    final remaining = target - funded;
    final progress = target <= 0 ? 0.0 : (funded / target).clamp(0.0, 1.0);
    final disclosure = _disclosures(listing['disclosures']);
    final expectedReturn = disclosure['expected_return_percent'];
    final risk = disclosure['risk_grade']?.toString() ?? 'Not rated';
    final repayment = disclosure['repayment_frequency']?.toString() ?? 'See agreement';

    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text(listing['purpose']?.toString() ?? 'Funding request', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold))),
            Chip(label: Text('Risk $risk')),
          ]),
          const SizedBox(height: 8),
          Text('${_money(target)} target · ${listing['term_days']} days'),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: _metric('Expected return', expectedReturn == null ? 'See disclosure' : '$expectedReturn%')),
            Expanded(child: _metric('Repayment', repayment)),
          ]),
          const SizedBox(height: 12),
          Text(disclosure['borrower_summary']?.toString() ?? 'Independently reviewed borrower information is available in the marketplace disclosure.'),
          const SizedBox(height: 12),
          LinearProgressIndicator(value: progress),
          const SizedBox(height: 6),
          Text('${_money(funded)} funded · ${_money(remaining)} remaining'),
          ExpansionTile(
            tilePadding: EdgeInsets.zero,
            title: const Text('Risk & full disclosures'),
            children: [
              _detail('Responsible lender', listing['lender_of_record']),
              _detail('Risk summary', disclosure['risk_summary']),
              _detail('Fees', disclosure['fees']),
              _detail('Loss treatment', disclosure['loss_allocation']),
              _detail('Custody / settlement', disclosure['custody']),
              _detail('Guarantee', disclosure['guarantee'] ?? 'No guarantee disclosed.'),
            ],
          ),
          SizedBox(width: double.infinity, child: FilledButton(onPressed: remaining > 0 ? () => _invest(listing) : null, child: const Text('Lend to this request'))),
        ]),
      ),
    );
  }

  Widget _metric(String label, String value) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, style: const TextStyle(fontSize: 12)), Text(value, style: const TextStyle(fontWeight: FontWeight.bold))]);

  Widget _detail(String label, dynamic value) => ListTile(contentPadding: EdgeInsets.zero, title: Text(label), subtitle: Text(value?.toString().isNotEmpty == true ? value.toString() : 'See formal agreement.'));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Peer lending')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _data,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
          if (snapshot.hasError) return Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [Text(snapshot.error.toString(), textAlign: TextAlign.center), const SizedBox(height: 12), FilledButton(onPressed: _refresh, child: const Text('Try again'))])));
          final data = snapshot.data ?? <String, dynamic>{};
          final listings = (data['listings'] as List? ?? const []).whereType<Map>().map((item) => item.cast<String, dynamic>()).toList();
          final overview = (data['overview'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
          final commitments = (overview['participatory_commitments'] as List? ?? const []);
          final requests = (overview['participatory_listings'] as List? ?? const []);

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(padding: const EdgeInsets.all(20), children: [
              const Text('Lend or borrow through one governed marketplace.', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text('Investor commitments and borrower requests stay separate from payment settlement. Risk and return are shown before money moves.'),
              const SizedBox(height: 16),
              Row(children: [
                Expanded(child: _summary('Open', listings.length.toString())),
                const SizedBox(width: 10),
                Expanded(child: _summary('Your lending', commitments.length.toString())),
                const SizedBox(width: 10),
                Expanded(child: _summary('Your requests', requests.length.toString())),
              ]),
              const SizedBox(height: 16),
              SizedBox(width: double.infinity, child: OutlinedButton.icon(onPressed: _requestFunding, icon: const Icon(Icons.request_quote_outlined), label: const Text('Borrow from investors'))),
              const SizedBox(height: 24),
              const Text('Opportunities', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
              const SizedBox(height: 10),
              if (listings.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(16), child: Text('No approved opportunities are open right now.'))) else ...listings.map(_opportunity),
              const SizedBox(height: 8),
              const Text('Expected returns are not guaranteed. Borrowers can default and investors can lose money according to the disclosed loss terms.'),
            ]),
          );
        },
      ),
    );
  }

  Widget _summary(String label, String value) => Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(children: [Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)), Text(label, textAlign: TextAlign.center)])));
}
