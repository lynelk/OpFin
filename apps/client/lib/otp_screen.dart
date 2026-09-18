import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/complete_registration_screen.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/login_screen.dart';
import 'package:sms_autofill/sms_autofill.dart';

class OtpScreen extends StatefulWidget {
  const OtpScreen({
    super.key,
    required this.phone,
    this.registration = false,
    this.resetPin,
  });
  final String phone;
  final bool registration;
  final String? resetPin;
  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> with CodeAutoFill {
  final _controller = TextEditingController();
  bool _loading = false;
  bool _resend = false;
  int _countdown = 300;
  bool _autoSubmitted = false;

  @override
  void initState() { super.initState(); listenForCode(); _tick(); }

  @override
  void codeUpdated() {
    final value = code;
    if (value != null && RegExp(r'^\d{6}$').hasMatch(value)) {
      _controller.text = value;
      if (!_autoSubmitted) { _autoSubmitted = true; _verify(); }
    }
  }

  void _tick() {
    Future.delayed(const Duration(seconds: 1), () {
      if (!mounted) return;
      if (_countdown > 0) { setState(() => _countdown--); _tick(); }
      else { setState(() => _resend = true); }
    });
  }

  Future<void> _verify() async {
    final otp = _controller.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(otp) || _loading) return;
    setState(() => _loading = true);
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/verify-otp'), body: {'phone': widget.phone, 'otp': otp});
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'The code did not work.');
      }
      if (widget.registration) {
        final token = ((decoded['data'] as Map?)?['verification_token'])?.toString();
        if (token == null || token.isEmpty) throw Exception('Phone verification failed.');
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(
          builder: (_) => CompleteRegistrationScreen(
            phone: widget.phone, verificationToken: token)));
        return;
      }
      if (widget.resetPin != null) {
        final reset = await http.post(Uri.parse('$apiUrl/reset-password'), body: {
          'phone': widget.phone, 'otp': otp,
          'pin': widget.resetPin, 'pin_confirmation': widget.resetPin,
        });
        final body = jsonDecode(reset.body) as Map<String, dynamic>;
        if (reset.statusCode != 200 || body['success'] != true) {
          throw Exception(body['message']?.toString() ?? 'Unable to reset PIN.');
        }
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Your new PIN is ready.')));
        Navigator.pushAndRemoveUntil(
          context, MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
      }
    } catch (error) {
      _autoSubmitted = false;
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _sendAgain() async {
    setState(() => _loading = true);
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/generate-otp'), body: {'phone': widget.phone});
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to send code.');
      }
      setState(() {
        _countdown = 300; _resend = false; _controller.clear(); _autoSubmitted = false;
      });
      listenForCode(); _tick();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() { cancel(); _controller.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Verify phone')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const Icon(Icons.sms_outlined, size: 64),
          const SizedBox(height: 24),
          const Text('Enter the 6-digit code',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Text('We sent it to ${widget.phone}. On supported phones, OpFin fills it in automatically.',
            textAlign: TextAlign.center),
          const SizedBox(height: 32),
          TextField(
            controller: _controller,
            keyboardType: TextInputType.number,
            maxLength: 6,
            autofillHints: const [AutofillHints.oneTimeCode],
            decoration: const InputDecoration(
              labelText: 'Verification code',
              hintText: '6 digits',
              border: OutlineInputBorder(),
            ),
            onChanged: (value) {
              if (value.length == 6 && !_autoSubmitted) {
                _autoSubmitted = true;
                _verify();
              }
            },
          ),
          const SizedBox(height: 18),
          Text(_countdown > 0 ? 'Code expires in $_countdown seconds' : 'Code expired',
            textAlign: TextAlign.center),
          const SizedBox(height: 24),
          SizedBox(
            height: 52,
            child: FilledButton(
              onPressed: _loading || _countdown <= 0 ? null : _verify,
              child: _loading
                ? const SizedBox(height:22,width:22,child:CircularProgressIndicator(strokeWidth:2))
                : const Text('Continue'),
            ),
          ),
          if (_resend) TextButton(
            onPressed: _loading ? null : _sendAgain,
            child: const Text('Send a new code')),
          const SizedBox(height: 12),
          const Text('Never share this code with anyone.', textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}
