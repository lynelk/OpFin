import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/loan_applications_screen.dart';
import 'package:opfin/services/user_session.dart';

/// Compatibility class name retained for existing navigation references.
/// The customer experience is intentionally a universal credit request rather
/// than a catalogue of internal loan products.
class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  final _amountController = TextEditingController();
  final _purposeController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _amountController.dispose();
    _purposeController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final amount = int.tryParse(_amountController.text.trim()) ?? 0;
    final purpose = _purposeController.text.trim();
    if (amount <= 0 || purpose.isEmpty) {
      _message('Enter the amount you need and what it is for.');
      return;
    }

    setState(() => _submitting = true);
    try {
      final token = await UserSession.getAccessToken();
      if (token == null || token.isEmpty) throw Exception('Secure session is required.');
      final response = await http.post(
        Uri.parse('$apiUrl/credit/applications'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({'amount_minor': amount, 'reason': purpose}),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode < 200 || response.statusCode >= 300 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to submit your credit request.');
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Request submitted for assessment.')));
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoanApplicationsScreen()));
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
      appBar: AppBar(title: const Text('Check credit options')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text('Tell us what you need', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          const Text('One short request is enough. OpFin selects an active configured credit route; you do not need to choose a lender, product code or internal term.'),
          const SizedBox(height: 22),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            autofocus: true,
            decoration: const InputDecoration(labelText: 'How much do you need? (UGX)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _purposeController,
            minLines: 3,
            maxLines: 4,
            decoration: const InputDecoration(labelText: 'What do you need it for?', hintText: 'For example: school fees, emergency, business stock', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 14),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text('After assessment, OpFin shows the responsible provider, amount received, every fee, interest, total repayment and repayment dates before you can accept anything.'),
            ),
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: _submitting ? null : _submit,
              child: Text(_submitting ? 'Submitting…' : 'Check my options'),
            ),
          ),
        ],
      ),
    );
  }
}
