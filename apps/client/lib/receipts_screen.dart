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
  final _money = NumberFormat('#,##0', 'en_US');
  late Future<List<Map<String, dynamic>>> _receipts;

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
            if (receipts.isEmpty) {
              return const Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('No completed transaction receipts yet.'),
                ),
              );
            }

            return ListView(
              padding: const EdgeInsets.all(20),
              children: receipts
                  .map(
                    (receipt) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.receipt_long_outlined),
                        title: Text(
                          'UGX ${_money.format(_amount(receipt['amount_minor']))}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        subtitle: Text(
                          '${receipt['receipt_type']?.toString().replaceAll('_', ' ') ?? 'Transaction'}\n'
                          '${receipt['receipt_number'] ?? ''} · ${receipt['status'] ?? ''}',
                        ),
                        isThreeLine: true,
                      ),
                    ),
                  )
                  .toList(),
            );
          },
        ),
      );
}
