import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/credit_offers_screen.dart';
import 'package:opfin/loan_application_result_screen.dart';
import 'package:opfin/services/user_session.dart';

class GuarantorsScreen extends StatefulWidget {
  const GuarantorsScreen({
    super.key,
    required this.applicationId,
    required this.requiredCount,
  });

  final int applicationId;
  final int requiredCount;

  @override
  State<GuarantorsScreen> createState() => _GuarantorsScreenState();
}

class _GuarantorsScreenState extends State<GuarantorsScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _code = TextEditingController();

  bool _loading = false;
  bool _codeSent = false;
  int _verified = 0;
  String? _normalisedPhone;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _code.dispose();
    super.dispose();
  }

  String _normalise(String value) {
    final phone = value.trim().replaceAll(' ', '');
    if (phone.startsWith('+256')) {
      return phone.substring(1);
    }
    if (phone.startsWith('256')) {
      return phone;
    }
    if (phone.startsWith('0') && phone.length == 10) {
      return '256' + phone.substring(1);
    }
    return phone;
  }

  Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please sign in again.');
    }
    return {
      'Authorization': 'Bearer ' + token,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  Future<void> _sendConsentCode() async {
    final phone = _normalise(_phone.text);
    if (!RegExp(r'^256\d{9}$').hasMatch(phone)) {
      _message('Enter a valid Ugandan guarantor phone number.');
      return;
    }

    setState(() => _loading = true);
    try {
      final response = await http.post(
        Uri.parse(
          apiUrl +
              '/credit/applications/' +
              widget.applicationId.toString() +
              '/guarantors/request-code',
        ),
        headers: await _headers(),
        body: jsonEncode({
          'phone': phone,
          'name': _name.text.trim().isEmpty ? null : _name.text.trim(),
        }),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode < 200 ||
          response.statusCode >= 300 ||
          decoded['success'] != true) {
        throw Exception(
          decoded['message']?.toString() ??
              'Unable to send the guarantor consent code.',
        );
      }

      setState(() {
        _normalisedPhone = phone;
        _codeSent = true;
        _code.clear();
      });
      _message(
        'A consent code was sent to the guarantor. Ask them to share it only if they agree.',
      );
    } catch (error) {
      _message(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _verifyAndAttach() async {
    final phone = _normalisedPhone;
    final otp = _code.text.trim();
    if (phone == null || !RegExp(r'^\d{6}$').hasMatch(otp)) {
      _message('Enter the 6-digit code received by the guarantor.');
      return;
    }

    setState(() => _loading = true);
    try {
      final verify = await http.post(
        Uri.parse(apiUrl + '/verify-otp'),
        body: {'phone': phone, 'otp': otp},
      );
      final verifyBody = jsonDecode(verify.body) as Map<String, dynamic>;
      if (verify.statusCode != 200 || verifyBody['success'] != true) {
        throw Exception(
          verifyBody['message']?.toString() ??
              'The guarantor consent code did not work.',
        );
      }

      final verificationToken =
          ((verifyBody['data'] as Map?)?['verification_token'])?.toString();
      if (verificationToken == null || verificationToken.isEmpty) {
        throw Exception('Guarantor verification could not be completed.');
      }

      final attach = await http.post(
        Uri.parse(
          apiUrl +
              '/credit/applications/' +
              widget.applicationId.toString() +
              '/guarantors',
        ),
        headers: await _headers(),
        body: jsonEncode({
          'phone': phone,
          'name': _name.text.trim().isEmpty ? null : _name.text.trim(),
          'verification_token': verificationToken,
          'consent_confirmed': true,
        }),
      );
      final body = jsonDecode(attach.body) as Map<String, dynamic>;
      if (attach.statusCode < 200 ||
          attach.statusCode >= 300 ||
          body['success'] != true) {
        throw Exception(
          body['message']?.toString() ?? 'Unable to record the guarantor.',
        );
      }

      final data =
          (body['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
      final remaining = (data['guarantors_remaining'] as num?)?.toInt() ?? 0;
      final verified = (data['guarantors_verified'] as num?)?.toInt() ??
          (_verified + 1);
      final next = data['next_state']?.toString();

      if (!mounted) {
        return;
      }

      setState(() => _verified = verified);

      if (remaining > 0 || next == 'guarantors_required') {
        _name.clear();
        _phone.clear();
        _code.clear();
        setState(() {
          _normalisedPhone = null;
          _codeSent = false;
        });
        _message(
          'Guarantor verified. ' +
              (remaining == 1
                  ? 'One more guarantor is required.'
                  : remaining.toString() + ' more guarantors are required.'),
        );
        return;
      }

      if (next == 'offer_ready') {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const CreditOffersScreen()),
        );
        return;
      }

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => LoanApplicationResultScreen(
            success: next != 'declined',
            message: next == 'declined'
                ? 'This loan request does not meet the current lending criteria.'
                : 'Your guarantors are verified. Your loan request is now being checked.',
          ),
        ),
      );
    } catch (error) {
      _message(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  void _message(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final remaining = (widget.requiredCount - _verified).clamp(0, 2);

    return Scaffold(
      appBar: AppBar(title: const Text('Verify guarantor')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'This loan needs a guarantor',
              style: TextStyle(fontSize: 25, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              widget.requiredCount == 1
                  ? 'One electronically verified guarantor is required for this product.'
                  : 'Two electronically verified guarantors are required for this product.',
            ),
            const SizedBox(height: 10),
            const Card(
              child: Padding(
                padding: EdgeInsets.all(14),
                child: Text(
                  'Enter the guarantor number yourself. OpFin does not read your contacts. The guarantor receives a message explaining the request. They should share the code only if they agree.',
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Verified: ' +
                  _verified.toString() +
                  ' of ' +
                  widget.requiredCount.toString() +
                  ' · Remaining: ' +
                  remaining.toString(),
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 20),
            TextField(
              controller: _name,
              enabled: !_codeSent && !_loading,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Guarantor name (optional)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _phone,
              enabled: !_codeSent && !_loading,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Guarantor phone number',
                hintText: '07XXXXXXXX',
                border: OutlineInputBorder(),
              ),
            ),
            if (_codeSent) ...[
              const SizedBox(height: 14),
              TextField(
                controller: _code,
                keyboardType: TextInputType.number,
                maxLength: 6,
                decoration: const InputDecoration(
                  labelText: 'Guarantor consent code',
                  hintText: '6 digits',
                  border: OutlineInputBorder(),
                ),
              ),
              const Text(
                'Only enter the code if the guarantor received the OpFin request and agreed to share it.',
              ),
            ],
            const SizedBox(height: 20),
            SizedBox(
              height: 52,
              child: FilledButton(
                onPressed: _loading
                    ? null
                    : (_codeSent ? _verifyAndAttach : _sendConsentCode),
                child: _loading
                    ? const CircularProgressIndicator(strokeWidth: 2)
                    : Text(
                        _codeSent
                            ? 'Verify guarantor'
                            : 'Send consent code',
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
