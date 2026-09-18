import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/services/credit_profile_api.dart';
import 'package:opfin/services/user_session.dart';

class LoanRepaymentScreen extends StatefulWidget {
  const LoanRepaymentScreen({
    super.key,
    required this.loanId,
    required this.repaymentAmount,
  });

  final int loanId;
  final int repaymentAmount;

  @override
  State<LoanRepaymentScreen> createState() => _LoanRepaymentScreenState();
}

class _LoanRepaymentScreenState extends State<LoanRepaymentScreen> {
  final _formKey = GlobalKey<FormState>();
  final _amount = TextEditingController();
  final _money = NumberFormat('#,##0', 'en_US');
  late Future<List<Map<String, dynamic>>> _wallets;
  int? _walletId;
  bool _loading = false;
  String? _statusMessage;

  @override
  void initState() {
    super.initState();
    _amount.text = widget.repaymentAmount.toString();
    _wallets = CreditProfileApi.wallets().then((items) {
      if (_walletId == null && items.isNotEmpty) {
        final defaults =
            items.where((item) => item['is_default_repayment'] == true).toList();
        _walletId =
            _n((defaults.isNotEmpty ? defaults.first : items.first)['id']);
      }
      return items;
    });
  }

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  int _n(dynamic value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  String _mask(String value) =>
      value.length <= 4 ? value : '•••• ${value.substring(value.length - 4)}';

  Future<void> _repay() async {
    if (!_formKey.currentState!.validate()) return;
    if (_walletId == null) {
      _message('Choose a verified repayment wallet.');
      return;
    }

    final amount = int.parse(_amount.text.trim().replaceAll(',', ''));
    final idempotency =
        'app-repayment:${widget.loanId}:${DateTime.now().microsecondsSinceEpoch}';

    setState(() {
      _loading = true;
      _statusMessage = null;
    });

    try {
      final token = await UserSession.getAccessToken();
      final response = await http.post(
        Uri.parse('$apiUrl/loans/${widget.loanId}/repay'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Idempotency-Key': idempotency,
        },
        body: jsonEncode({
          'amount_minor': amount,
          'wallet_id': _walletId,
          'idempotency_key': idempotency,
        }),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode < 200 ||
          response.statusCode >= 300 ||
          decoded['success'] != true) {
        throw Exception(
            decoded['message']?.toString() ?? 'Unable to start repayment.');
      }

      final data =
          (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
      final status = data['status']?.toString() ?? 'pending';
      setState(() {
        _statusMessage = status == 'successful'
            ? 'Payment confirmed. Your balance will refresh now.'
            : 'A payment request was sent to your wallet. Confirm it on your phone. Your OpFin balance updates only after the payment provider confirms success.';
      });
    } catch (error) {
      _message(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _message(String value) {
    if (mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(value)));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Repay loan')),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _wallets,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(child: Text(snapshot.error.toString()));
            }
            final wallets = snapshot.data ?? const [];
            return SafeArea(
              child: ListView(
                padding: const EdgeInsets.all(24),
                children: [
                  const Semantics(
                    header: true,
                    child: Text(
                      'How much will you repay?',
                      style:
                          TextStyle(fontSize: 25, fontWeight: FontWeight.w800),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Outstanding: UGX ${_money.format(widget.repaymentAmount)}',
                    style: const TextStyle(
                        fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 24),
                  Form(
                    key: _formKey,
                    child: TextFormField(
                      controller: _amount,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Repayment amount',
                        prefixText: 'UGX ',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        final entered =
                            int.tryParse((value ?? '').replaceAll(',', '')) ?? 0;
                        if (entered <= 0) return 'Enter an amount greater than zero';
                        if (entered > widget.repaymentAmount) {
                          return 'You cannot repay more than the outstanding amount';
                        }
                        return null;
                      },
                    ),
                  ),
                  const SizedBox(height: 18),
                  DropdownButtonFormField<int>(
                    key: ValueKey(_walletId),
                    initialValue: _walletId,
                    decoration: const InputDecoration(
                      labelText: 'Pay from',
                      border: OutlineInputBorder(),
                    ),
                    items: wallets
                        .map(
                          (wallet) => DropdownMenuItem(
                            value: _n(wallet['id']),
                            child: Text(
                              '${wallet['provider'] ?? 'Mobile money'} · ${_mask(wallet['msisdn']?.toString() ?? '')}',
                            ),
                          ),
                        )
                        .toList(),
                    onChanged:
                        _loading ? null : (value) => setState(() => _walletId = value),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'OpFin will send a collection request. A request is not a completed repayment until the provider confirms it.',
                    style: TextStyle(color: OpFinColors.muted),
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    height: 54,
                    child: FilledButton(
                      onPressed: _loading ? null : _repay,
                      child: _loading
                          ? const CircularProgressIndicator(strokeWidth: 2)
                          : const Text('Send payment request'),
                    ),
                  ),
                  if (_statusMessage != null) ...[
                    const SizedBox(height: 20),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(Icons.info_outline),
                            const SizedBox(width: 10),
                            Expanded(child: Text(_statusMessage!)),
                          ],
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            );
          },
        ),
      );
}
