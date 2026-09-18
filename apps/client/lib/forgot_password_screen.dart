import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/otp_screen.dart';
import 'package:sms_autofill/sms_autofill.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});
  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _phone = TextEditingController();
  final _pin = TextEditingController();
  final _confirm = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;

  String _normalise(String value) {
    final p = value.trim().replaceAll(' ', '');
    if (p.startsWith('+256')) return p.substring(1);
    if (p.startsWith('256')) return p;
    return '256${p.substring(1)}';
  }

  Future<void> _send() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    try {
      String signature = '';
      if (Platform.isAndroid) {
        signature = await SmsAutoFill().getAppSignature;
      }
      final response = await http.post(Uri.parse('$apiUrl/generate-otp'), body: {
        'phone': _normalise(_phone.text),
        if (signature.isNotEmpty) 'app_signature': signature,
      });
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to send code.');
      }
      if (!mounted) {
        return;
      }
      Navigator.push(context, MaterialPageRoute(
        builder: (_) => OtpScreen(
          phone: _normalise(_phone.text), resetPin: _pin.text)));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Reset PIN')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const Text('Create a new PIN',
            style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          const Text('We will confirm your phone before changing your PIN.'),
          const SizedBox(height: 24),
          Form(key: _formKey, child: Column(children: [
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(labelText: 'Phone number'),
              validator: (value) {
                final v=value?.trim().replaceAll(' ','')??'';
                return RegExp(r'^(0\d{9}|256\d{9}|\+256\d{9})$').hasMatch(v)
                  ? null : 'Enter a valid phone number';
              }),
            const SizedBox(height: 16),
            TextFormField(
              controller: _pin,
              keyboardType: TextInputType.number,
              obscureText: true,
              maxLength: 6,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(labelText: 'New 6-digit PIN'),
              validator: (v)=>RegExp(r'^\d{6}$').hasMatch(v??'')?null:'Enter 6 digits'),
            const SizedBox(height: 12),
            TextFormField(
              controller: _confirm,
              keyboardType: TextInputType.number,
              obscureText: true,
              maxLength: 6,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(labelText: 'Confirm new PIN'),
              validator: (v)=>v==_pin.text?null:'PINs do not match'),
            const SizedBox(height: 24),
            SizedBox(width: double.infinity,height:52,child:FilledButton(
              onPressed:_loading?null:_send,
              child:_loading?const CircularProgressIndicator(strokeWidth:2)
                :const Text('Send verification code'))),
          ])),
        ],
      ),
    ),
  );
}
