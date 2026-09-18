import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class TermVariationsScreen extends StatefulWidget {
  const TermVariationsScreen({super.key});

  @override
  State<TermVariationsScreen> createState() => _TermVariationsScreenState();
}

class _TermVariationsScreenState extends State<TermVariationsScreen> {
  late Future<List<Map<String, dynamic>>> _variations;

  @override
  void initState() {
    super.initState();
    _variations = _load();
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

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await http.get(
      Uri.parse('$apiUrl/credit/term-variations'),
      headers: await _headers(),
    );
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode != 200 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to load term variations.');
    }

    final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? {};
    return (data['variations'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  Future<void> _accept(Map<String, dynamic> variation) async {
    final hash = variation['consent_hash']?.toString() ?? '';
    if (hash.isEmpty) {
      _message('This variation does not have a valid consent record.');
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Accept this change?'),
        content: const Text(
          'Only accept if you understand and agree to the exact changes shown. Your existing accepted offer remains part of the audit record.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Not now'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('I agree'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    final response = await http.post(
      Uri.parse(
        '$apiUrl/credit/term-variations/' +
            variation['id'].toString() +
            '/accept',
      ),
      headers: await _headers(),
      body: jsonEncode({
        'accept_terms': true,
        'variation_hash': hash,
      }),
    );
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 ||
        response.statusCode >= 300 ||
        decoded['success'] != true) {
      _message(decoded['message']?.toString() ?? 'Unable to record consent.');
      return;
    }

    _message('Your consent has been recorded.');
    setState(() => _variations = _load());
  }

  void _message(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Changes to loan terms')),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _variations,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(child: Text(snapshot.error.toString()));
            }

            final variations = snapshot.data ?? const [];
            return ListView(
              padding: const EdgeInsets.all(20),
              children: [
                const Text(
                  'You control changes to your loan terms',
                  style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                const Text(
                  'OpFin does not change accepted credit terms silently. Any variation requires your consent. An interest-rate change also requires prior UMRA approval before it can be presented to you.',
                ),
                const SizedBox(height: 18),
                if (variations.isEmpty)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text('There are no proposed changes to your loan terms.'),
                    ),
                  ),
                ...variations.map((variation) {
                  final changes = variation['proposed_changes'] is Map
                      ? (variation['proposed_changes'] as Map).cast<String, dynamic>()
                      : <String, dynamic>{};
                  final status = variation['status']?.toString() ?? '';
                  final requiresApproval =
                      variation['requires_umra_approval'] == true;
                  final approval = variation['umra_approval_reference'];

                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Loan ' + variation['loan_id'].toString(),
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text('Status: ' + status.replaceAll('_', ' ')),
                          const SizedBox(height: 10),
                          Text(variation['reason']?.toString() ?? ''),
                          const Divider(height: 24),
                          ...changes.entries.map(
                            (entry) => ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(entry.key.replaceAll('_', ' ')),
                              trailing: Text(
                                entry.value.toString(),
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ),
                          if (requiresApproval)
                            Text(
                              approval == null
                                  ? 'Prior UMRA approval has not yet been recorded.'
                                  : 'UMRA approval reference: ' + approval.toString(),
                            ),
                          const SizedBox(height: 12),
                          if (status == 'proposed' ||
                              status == 'umra_approved')
                            SizedBox(
                              width: double.infinity,
                              child: FilledButton(
                                onPressed: requiresApproval && approval == null
                                    ? null
                                    : () => _accept(variation),
                                child: const Text('Review and accept change'),
                              ),
                            ),
                          if (variation['customer_consented_at'] != null)
                            const Text(
                              'Your consent is recorded.',
                              style: TextStyle(fontWeight: FontWeight.bold),
                            ),
                        ],
                      ),
                    ),
                  );
                }),
              ],
            );
          },
        ),
      );
}
