import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/services/user_session.dart';

class AccountDeleteScreen extends StatefulWidget {
  const AccountDeleteScreen({super.key});

  @override
  State<AccountDeleteScreen> createState() => _AccountDeleteScreenState();
}

class _AccountDeleteScreenState extends State<AccountDeleteScreen> {
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _deleteAccount() async {
    if (_password.text.isEmpty || _confirmation.text.trim() != 'DELETE') {
      _message('Enter your current password and type DELETE exactly.');
      return;
    }

    setState(() => _submitting = true);
    try {
      final token = await UserSession.getAccessToken();
      if (token == null || token.isEmpty) throw Exception('Secure session is required.');
      final response = await http.delete(
        Uri.parse('$apiUrl/account'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({'password': _password.text, 'confirmation': 'DELETE'}),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if ((response.statusCode != 200 && response.statusCode != 202) || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to delete your account.');
      }
      final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
      if (data['deletion_status'] == 'pending_obligations') {
        if (!mounted) return;
        await showDialog<void>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Deletion request recorded'),
            content: Text('${data['message'] ?? 'Your account deletion request is recorded.'}${data['case_number'] != null ? '\n\nCase: ${data['case_number']}' : ''}'),
            actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Close'))],
          ),
        );
        return;
      }

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

  void _message(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message.replaceFirst('Exception: ', ''))));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Delete account')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text('Delete your OpFin account', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),
          const Text('If there are no active regulated or financial obligations, deletion completes immediately. If an active obligation must first be closed, OpFin records the request and completes deletion through the regulated closure process.'),
          const SizedBox(height: 16),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text('Optional customer context and active access are removed when deletion completes. Financial, KYC/AML, accounting, credit-reporting, reconciliation, fraud-prevention, dispute and audit records may be retained only where applicable law or regulation requires them, and only for the required retention period.'),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _password,
            obscureText: true,
            autofillHints: const [AutofillHints.password],
            decoration: const InputDecoration(labelText: 'Current password', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _confirmation,
            autocorrect: false,
            enableSuggestions: false,
            decoration: const InputDecoration(labelText: 'Type DELETE to confirm', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 18),
          FilledButton(
            onPressed: _submitting ? null : _deleteAccount,
            child: Text(_submitting ? 'Deleting…' : 'Delete my account'),
          ),
        ],
      ),
    );
  }
}
