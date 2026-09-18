import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class ReceiptsScreen extends StatefulWidget {
  const ReceiptsScreen({super.key});

  @override
  State<ReceiptsScreen> createState() => _ReceiptsScreenState();
}

class _ReceiptsScreenState extends State<ReceiptsScreen> {
  late Future<List<Map<String, dynamic>>> _receipts;
  final _money = NumberFormat('#,##0', 'en_US');

  @override
  void initState() {
    super.initState();
    _receipts = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final token = await UserSession.getAccessToken();
    final response = await http.get(
      Uri.parse('$apiUrl/receipts'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode != 200 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to load receipts.');
    }

    final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? {};
    return (data['receipts'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  int _amount(dynamic value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  String _ugx(dynamic value) => 'UGX ' + _money.format(_amount(value));

  void _showReceipt(Map<String, dynamic> receipt) {
    final payload = receipt['payload'] is Map
        ? (receipt['payload'] as Map).cast<String, dynamic>()
        : <String, dynamic>{};

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'OpFin transaction receipt',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 14),
              _ReceiptRow('Receipt', receipt['receipt_reference']?.toString() ?? ''),
              _ReceiptRow('Transaction', (receipt['transaction_type'] ?? '').toString()),
              _ReceiptRow('Amount', _ugx(receipt['amount_minor'])),
              _ReceiptRow('Status', (receipt['status'] ?? '').toString()),
              _ReceiptRow('Provider', (receipt['provider'] ?? '').toString()),
              _ReceiptRow('Provider reference', (receipt['provider_reference'] ?? '—').toString()),
              _ReceiptRow('Completed', (payload['completed_at'] ?? receipt['issued_at'] ?? '').toString()),
              _ReceiptRow('Evidence hash', (receipt['payload_hash'] ?? '').toString()),
              const SizedBox(height: 12),
              const Text(
                'This receipt is generated from a provider-confirmed transaction. A payment or disbursement request that is still pending does not receive a successful receipt.',
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Close'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Receipts')),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _receipts,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(child: Text(snapshot.error.toString()));
            }
            final receipts = snapshot.data ?? const [];
            return RefreshIndicator(
              onRefresh: () async {
                setState(() => _receipts = _load());
                await _receipts;
              },
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text(
                    'Transaction receipts',
                    style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Successful disbursements and repayments are acknowledged here and by instant message where delivery is available.',
                  ),
                  const SizedBox(height: 18),
                  if (receipts.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('No completed transaction receipts yet.'),
                      ),
                    ),
                  ...receipts.map(
                    (receipt) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.receipt_long_outlined),
                        title: Text(
                          _ugx(receipt['amount_minor']),
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        subtitle: Text(
                          (receipt['transaction_type'] ?? '').toString() +
                              ' · ' +
                              (receipt['receipt_reference'] ?? '').toString(),
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => _showReceipt(receipt),
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

class _ReceiptRow extends StatelessWidget {
  const _ReceiptRow(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: Text(label)),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                value,
                textAlign: TextAlign.end,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
      );
}
