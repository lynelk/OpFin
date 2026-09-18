import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/otp_screen.dart';
import 'package:sms_autofill/sms_autofill.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _phone = TextEditingController();
  bool _accepted = false;
  bool _loading = false;

  String _normalise(String value) {
    final phone = value.trim().replaceAll(' ', '');
    if (phone.startsWith('+256')) return phone.substring(1);
    if (phone.startsWith('256')) return phone;
    return '256${phone.substring(1)}';
  }

  Future<void> _sendOtp() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_accepted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Accept the Terms and Privacy Notice to continue.')),
      );
      return;
    }
    setState(() => _loading = true);
    try {
      String signature = '';
      if (Platform.isAndroid) signature = await SmsAutoFill().getAppSignature;
      final response = await http.post(
        Uri.parse('$apiUrl/generate-otp'),
        body: {
          'phone': _normalise(_phone.text),
          if (signature.isNotEmpty) 'app_signature': signature,
        },
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to send code.');
      }
      if (!mounted) return;
      Navigator.push(context, MaterialPageRoute(
        builder: (_) => OtpScreen(phone: _normalise(_phone.text), registration: true),
      ));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() { _phone.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: OpFinColors.ivory,
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 28),
          const Icon(Icons.account_balance_wallet_outlined,
              size: 64, color: OpFinColors.indigo),
          const SizedBox(height: 24),
          const Semantics(
            header: true,
            child: Text('Start with your phone',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800)),
          ),
          const SizedBox(height: 10),
          const Text(
            'We will send a 6-digit code to confirm the number belongs to you.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 16, color: OpFinColors.muted),
          ),
          const SizedBox(height: 34),
          Form(
            key: _formKey,
            child: TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              autofillHints: const [AutofillHints.telephoneNumber],
              decoration: const InputDecoration(
                labelText: 'Phone number', hintText: '07XXXXXXXX',
                prefixIcon: Icon(Icons.phone_outlined)),
              validator: (value) {
                final phone = value?.trim().replaceAll(' ', '') ?? '';
                return RegExp(r'^(0\d{9}|256\d{9}|\+256\d{9})$').hasMatch(phone)
                  ? null : 'Enter a valid Ugandan phone number';
              },
            ),
          ),
          const SizedBox(height: 18),
          CheckboxListTile(
            value: _accepted,
            onChanged: (v) => setState(() => _accepted = v == true),
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            title: const Text('I agree to the Terms and Privacy Notice.'),
            subtitle: const Text('Permission to check credit information is requested separately.'),
          ),
          const SizedBox(height: 22),
          SizedBox(
            height: 52,
            child: FilledButton(
              onPressed: _loading ? null : _sendOtp,
              child: _loading
                ? const SizedBox(height:22,width:22,child:CircularProgressIndicator(strokeWidth:2))
                : const Text('Send code'),
            ),
          ),
          const SizedBox(height: 20),
          TextButton(
            onPressed: () => Navigator.pushReplacement(
              context, MaterialPageRoute(builder: (_) => const LoginScreen())),
            child: const Text('I already have an account'),
          ),
        ],
      ),
    ),
  );
}
