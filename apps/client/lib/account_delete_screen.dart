import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/services/offline_sync_service.dart';
import 'package:opfin/services/user_session.dart';

class AccountDeleteScreen extends StatefulWidget {
  const AccountDeleteScreen({super.key});

  @override
  State<AccountDeleteScreen> createState() => _AccountDeleteScreenState();
}

class _AccountDeleteScreenState extends State<AccountDeleteScreen> {
  final _pin = TextEditingController();
  final _confirmation = TextEditingController();
  final _money = NumberFormat('#,##0', 'en_US');
  bool _submitting = false;
  bool _loading = true;
  Map<String, dynamic> _readiness = <String, dynamic>{};
  final Set<String> _selectedData = <String>{};

  @override
  void initState() {
    super.initState();
    _loadReadiness();
  }

  @override
  void dispose() {
    _pin.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Secure session is required.');
    return {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  Future<void> _loadReadiness() async {
    setState(() => _loading = true);
    try {
      final response = await http.get(
        Uri.parse('$apiUrl/account/deletion-readiness'),
        headers: await _headers(),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to check account deletion readiness.');
      }
      if (!mounted) return;
      setState(() {
        _readiness = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
      });
    } catch (error) {
      _message(error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _obligations =>
      (_readiness['active_obligations'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => item.cast<String, dynamic>())
          .toList();

  List<Map<String, dynamic>> get _dataCategories =>
      (((_readiness['data_deletion'] as Map?)?['available_categories'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => item.cast<String, dynamic>())
          .toList();

  bool get _canDeleteAccount => _readiness['can_delete_account'] == true;

  Future<void> _deleteAccount() async {
    if (_pin.text.isEmpty || _confirmation.text.trim() != 'DELETE') {
      _message('Enter your current PIN and type DELETE exactly.');
      return;
    }

    setState(() => _submitting = true);
    try {
      final response = await http.delete(
        Uri.parse('$apiUrl/account'),
        headers: await _headers(),
        body: jsonEncode({'pin': _pin.text, 'confirmation': 'DELETE'}),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};

      if (response.statusCode == 409 && data['deletion_status'] == 'blocked_obligations') {
        if (!mounted) return;
        setState(() => _readiness = {
          ..._readiness,
          'can_delete_account': false,
          'active_obligations': data['active_obligations'] ?? const [],
          'active_obligation_count': data['active_obligation_count'] ?? 0,
        });
        await _showBlockedDialog(data);
        return;
      }

      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to delete your account.');
      }

      await OfflineSyncService.clearLocalData();
      await UserSession.clear();
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (_) => false,
      );
    } catch (error) {
      _message(error.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _deleteSelectedData() async {
    if (_pin.text.isEmpty) {
      _message('Enter your current PIN.');
      return;
    }
    if (_selectedData.isEmpty) {
      _message('Select at least one data category to delete.');
      return;
    }

    setState(() => _submitting = true);
    try {
      final response = await http.delete(
        Uri.parse('$apiUrl/account/data'),
        headers: await _headers(),
        body: jsonEncode({
          'pin': _pin.text,
          'confirmation': 'DELETE_DATA',
          'data_categories': _selectedData.toList(),
        }),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to delete the selected data.');
      }
      final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Selected data deleted'),
          content: Text(data['message']?.toString() ?? 'The selected optional data has been deleted.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Close')),
          ],
        ),
      );
      _selectedData.clear();
      await _loadReadiness();
    } catch (error) {
      _message(error.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _showBlockedDialog(Map<String, dynamic> data) async {
    final items = (data['active_obligations'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Account deletion blocked'),
        content: SizedBox(
          width: 480,
          child: ListView(
            shrinkWrap: true,
            children: [
              Text(data['message']?.toString() ??
                  'Resolve the listed obligations before trying account deletion again.'),
              const SizedBox(height: 12),
              ...items.map(_obligationSummary),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Close')),
        ],
      ),
    );
  }

  Widget _obligationSummary(Map<String, dynamic> item) {
    final provider = (item['provider'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    final amount = item['amount_minor'] is num ? (item['amount_minor'] as num).toInt() : null;
    final currency = item['currency']?.toString() ?? 'UGX';
    final contacts = <String>[
      if ((provider['phone']?.toString() ?? '').isNotEmpty) provider['phone'].toString(),
      if ((provider['email']?.toString() ?? '').isNotEmpty) provider['email'].toString(),
      if ((provider['address']?.toString() ?? '').isNotEmpty) provider['address'].toString(),
    ];

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(item['label']?.toString() ?? 'Outstanding obligation',
                style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            Text('Status: ${item['status'] ?? 'active'}'),
            if ((item['reference']?.toString() ?? '').isNotEmpty)
              Text('Reference: ${item['reference']}'),
            if (amount != null) Text('Amount: $currency ${_money.format(amount)}'),
            if ((item['due_date']?.toString() ?? '').isNotEmpty)
              Text('Due / relevant date: ${item['due_date']}'),
            const SizedBox(height: 4),
            Text('Provider: ${provider['name'] ?? 'Provider'}'),
            if (contacts.isNotEmpty) Text('Contact: ${contacts.join(' · ')}'),
            if (contacts.isEmpty)
              const Text('Direct provider contact is not recorded in OpFin. Use the provider reference above when seeking closure support.'),
          ],
        ),
      ),
    );
  }

  void _message(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message.replaceFirst('Exception: ', ''))),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Privacy & account deletion')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadReadiness,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text('Delete account or optional data',
                      style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 10),
                  const Text(
                    'You can delete your whole OpFin account, or remove selected optional data while keeping your account. Account deletion is blocked immediately if any active financial obligation remains.',
                  ),
                  const SizedBox(height: 16),
                  if (_obligations.isNotEmpty) ...[
                    const Text('Obligations that must be resolved first',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    ..._obligations.map(_obligationSummary),
                    const SizedBox(height: 12),
                  ],
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text(
                        'Financial, KYC/AML, accounting, credit-reporting, settlement, fraud-prevention, dispute and audit records may be retained only where law, regulation or financial-control requirements require them, and only for the applicable retention period.',
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  TextField(
                    controller: _pin,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Current 6-digit PIN',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 22),
                  const Text('Delete selected optional data',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 6),
                  const Text('This keeps your OpFin account active. Select only the optional data you want removed.'),
                  const SizedBox(height: 8),
                  ..._dataCategories.map((category) {
                    final code = category['code']?.toString() ?? '';
                    return CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _selectedData.contains(code),
                      title: Text(category['label']?.toString() ?? code),
                      subtitle: Text(category['description']?.toString() ?? ''),
                      onChanged: _submitting
                          ? null
                          : (value) => setState(() {
                                if (value == true) {
                                  _selectedData.add(code);
                                } else {
                                  _selectedData.remove(code);
                                }
                              }),
                    );
                  }),
                  const SizedBox(height: 8),
                  OutlinedButton(
                    onPressed: _submitting || _selectedData.isEmpty ? null : _deleteSelectedData,
                    child: const Text('Delete selected data'),
                  ),
                  const Divider(height: 40),
                  const Text('Delete entire account',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 6),
                  Text(
                    _canDeleteAccount
                        ? 'No active financial obligation currently prevents deletion.'
                        : 'Deletion cannot proceed until every obligation shown above is resolved.',
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _confirmation,
                    autocorrect: false,
                    enableSuggestions: false,
                    enabled: _canDeleteAccount && !_submitting,
                    decoration: const InputDecoration(
                      labelText: 'Type DELETE to confirm',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 14),
                  FilledButton(
                    onPressed: !_canDeleteAccount || _submitting ? null : _deleteAccount,
                    child: Text(_submitting ? 'Working…' : 'Delete my account'),
                  ),
                ],
              ),
            ),
    );
  }
}
