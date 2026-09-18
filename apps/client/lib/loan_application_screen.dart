import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/credit_offers_screen.dart';
import 'package:opfin/loan_application_result_screen.dart';
import 'package:opfin/services/credit_profile_api.dart';
import 'package:opfin/services/user_session.dart';

class LoanApplicationScreen extends StatefulWidget {
  const LoanApplicationScreen({
    super.key,
    this.loanProductId,
    this.loanProductTermId,
    this.institutionId,
    this.initialAmount,
  });

  final int? loanProductId;
  final int? loanProductTermId;
  final int? institutionId;
  final int? initialAmount;

  @override
  State<LoanApplicationScreen> createState() => _LoanApplicationScreenState();
}

class _LoanApplicationScreenState extends State<LoanApplicationScreen> {
  final _money = NumberFormat('#,##0', 'en_US');
  final _amount = TextEditingController();
  late Future<Map<String, dynamic>> _load;
  bool _submitting = false;
  String? _reason;
  int? _selectedTermId;

  static const _reasons = [
    'School or education',
    'Medical or health',
    'Business stock or working capital',
    'Home or household need',
    'Emergency',
    'Transport',
    'Other personal need',
  ];

  String get _channel => Platform.isIOS ? 'app_store' : 'play_store';

  @override
  void initState() {
    super.initState();
    if (widget.initialAmount != null && widget.initialAmount! > 0) {
      _amount.text = widget.initialAmount.toString();
    }
    _load = _loadData();
  }

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please sign in again.');
    }
    return {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  Future<Map<String, dynamic>> _loadData() async {
    final profile = await CreditProfileApi.load();
    final response = await http.get(
      Uri.parse('$apiUrl/credit/options?distribution_channel=$_channel'),
      headers: await _headers(),
    );
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode != 200 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ??
          'Unable to load repayment options.');
    }
    final data =
        (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    final options = (data['options'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();

    if (_selectedTermId == null && options.isNotEmpty) {
      final legacy = widget.loanProductTermId;
      final selected = legacy != null
          ? options.where((item) => _n(item['loan_product_term_id']) == legacy)
          : const <Map<String, dynamic>>[];
      _selectedTermId = selected.isNotEmpty
          ? _n(selected.first['loan_product_term_id'])
          : _n(options.first['loan_product_term_id']);
    }

    return {'profile_state': profile, 'options': options};
  }

  int _n(dynamic value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  String _ugx(dynamic value) => 'UGX ${_money.format(_n(value))}';

  Map<String, dynamic>? _selectedOption(List<Map<String, dynamic>> options) {
    for (final option in options) {
      if (_n(option['loan_product_term_id']) == _selectedTermId) return option;
    }
    return options.isEmpty ? null : options.first;
  }

  Future<void> _submit(
    Map<String, dynamic> profileState,
    List<Map<String, dynamic>> options,
  ) async {
    final profile =
        (profileState['profile'] as Map?)?.cast<String, dynamic>() ?? {};
    final available = _n(profile['available_to_borrow_minor']);
    final amount = int.tryParse(_amount.text.trim().replaceAll(',', '')) ?? 0;
    final option = _selectedOption(options);

    if (amount <= 0) {
      _message('Enter how much you want to borrow.');
      return;
    }
    if (amount > available) {
      _message('Choose an amount up to ${_ugx(available)}.');
      return;
    }
    if (_reason == null) {
      _message('Choose what the loan is for.');
      return;
    }
    if (option == null) {
      _message('No eligible repayment option is available right now.');
      return;
    }

    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Semantics(
                header: true,
                child: Text(
                  'Check your request',
                  style: TextStyle(fontSize: 23, fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(height: 16),
              _ReviewRow('Amount', _ugx(amount)),
              _ReviewRow(
                  'Repayment period', '${_n(option['duration_days'])} days'),
              _ReviewRow('Purpose', _reason!),
              const SizedBox(height: 12),
              const Text(
                'This is a request, not yet a loan. If approved, OpFin will show the exact amount you receive, interest, fees, APR where required, total repayment and payment dates before you accept.',
                style: TextStyle(height: 1.45),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: FilledButton(
                  onPressed: () => Navigator.pop(context, true),
                  child: const Text('Submit request'),
                ),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Go back and edit'),
              ),
            ],
          ),
        ),
      ),
    );

    if (confirmed != true) return;
    setState(() => _submitting = true);
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/credit/applications'),
        headers: await _headers(),
        body: jsonEncode({
          'loan_product_id': _n(option['loan_product_id']),
          'loan_product_term_id': _n(option['loan_product_term_id']),
          'institution_id': _n(option['institution_id']),
          'amount_minor': amount,
          'reason': _reason,
          'distribution_channel': _channel,
        }),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode < 200 ||
          response.statusCode >= 300 ||
          decoded['success'] != true) {
        throw Exception(
            decoded['message']?.toString() ?? 'Unable to submit loan request.');
      }
      final data =
          (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
      final next = data['next_state']?.toString();

      if (!mounted) return;
      if (next == 'offer_ready') {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const CreditOffersScreen()),
        );
      } else {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => LoanApplicationResultScreen(
              success: next != 'declined',
              message: next == 'declined'
                  ? 'This request does not meet the current lending criteria.'
                  : 'Your request is being checked. You can follow it under Activity.',
            ),
          ),
        );
      }
    } catch (error) {
      _message(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _message(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Apply for a loan')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _load,
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
                        onPressed: () => setState(() => _load = _loadData()),
                        child: const Text('Try again'),
                      ),
                    ],
                  ),
                ),
              );
            }

            final data = snapshot.data ?? {};
            final profileState =
                (data['profile_state'] as Map).cast<String, dynamic>();
            final profile =
                (profileState['profile'] as Map?)?.cast<String, dynamic>() ?? {};
            final options = (data['options'] as List)
                .whereType<Map>()
                .map((item) => item.cast<String, dynamic>())
                .toList();
            final available = _n(profile['available_to_borrow_minor']);
            final due = _n(profile['amount_due_minor']);
            final selected = _selectedOption(options);

            return ListView(
              padding: const EdgeInsets.all(20),
              children: [
                const Semantics(
                  header: true,
                  child: Text(
                    'How much do you need?',
                    style: TextStyle(fontSize: 25, fontWeight: FontWeight.w800),
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Choose an amount within your limit. You will see every cost before accepting a loan.',
                ),
                const SizedBox(height: 20),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Row(
                      children: [
                        Expanded(
                          child: _Metric(
                            label: 'Available limit',
                            value: _ugx(available),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _Metric(
                            label: 'Amount due',
                            value: _ugx(due),
                            warning: due > 0,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                if (due > 0) ...[
                  const SizedBox(height: 8),
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text(
                        'Repay the amount due before starting another loan request.',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 18),
                TextField(
                  controller: _amount,
                  enabled: !_submitting && due == 0 && available > 0,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Loan amount',
                    prefixText: 'UGX ',
                    helperText: 'Maximum ${_ugx(available)}',
                    border: const OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<int>(
                  key: ValueKey(_selectedTermId),
                  initialValue: _selectedTermId,
                  decoration: const InputDecoration(
                    labelText: 'Repayment period',
                    border: OutlineInputBorder(),
                  ),
                  items: options
                      .map(
                        (option) => DropdownMenuItem(
                          value: _n(option['loan_product_term_id']),
                          child: Text('${_n(option['duration_days'])} days'),
                        ),
                      )
                      .toList(),
                  onChanged: _submitting || due > 0
                      ? null
                      : (value) => setState(() => _selectedTermId = value),
                ),
                if (selected != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    'Payments: ${selected['repayment_frequency'] ?? 'As scheduled'}',
                    style: const TextStyle(color: OpFinColors.muted),
                  ),
                ],
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: _reason,
                  decoration: const InputDecoration(
                    labelText: 'What is the loan for?',
                    border: OutlineInputBorder(),
                  ),
                  items: _reasons
                      .map((reason) =>
                          DropdownMenuItem(value: reason, child: Text(reason)))
                      .toList(),
                  onChanged: _submitting || due > 0
                      ? null
                      : (value) => setState(() => _reason = value),
                ),
                const SizedBox(height: 18),
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.info_outline),
                        SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Submitting this request does not move money. If approved, you must review and accept a separate formal offer before disbursement.',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                SizedBox(
                  height: 54,
                  child: FilledButton(
                    onPressed: _submitting || due > 0 || available <= 0
                        ? null
                        : () => _submit(profileState, options),
                    child: _submitting
                        ? const CircularProgressIndicator(strokeWidth: 2)
                        : const Text('Continue'),
                  ),
                ),
              ],
            );
          },
        ),
      );
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.label,
    required this.value,
    this.warning = false,
  });

  final String label;
  final String value;
  final bool warning;

  @override
  Widget build(BuildContext context) => Semantics(
        label: '$label, $value',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(color: OpFinColors.muted)),
            const SizedBox(height: 4),
            Text(
              value,
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: warning ? OpFinColors.danger : OpFinColors.ink,
              ),
            ),
          ],
        ),
      );
}

class _ReviewRow extends StatelessWidget {
  const _ReviewRow(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(
          children: [
            Expanded(child: Text(label)),
            const SizedBox(width: 12),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.end,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      );
}
