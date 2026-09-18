import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/forgot_password_screen.dart';
import 'package:opfin/home_screen.dart';
import 'package:opfin/register_screen.dart';
import 'package:opfin/services/user_session.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _phone = TextEditingController();
  final _pin = TextEditingController();
  bool _loading = false;

  String _normalise(String value) {
    final p = value.trim().replaceAll(' ', '');
    if (p.startsWith('+256')) return p.substring(1);
    if (p.startsWith('256')) return p;
    return '256${p.substring(1)}';
  }

  Future<void> _login() async {
    final phone = _phone.text.trim().replaceAll(' ', '');
    if (!RegExp(r'^(0\d{9}|256\d{9}|\+256\d{9})$').hasMatch(phone) ||
        !RegExp(r'^\d{6}$').hasMatch(_pin.text)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter your phone number and 6-digit PIN.')));
      return;
    }
    setState(() => _loading = true);
    try {
      final response = await http.post(Uri.parse('$apiUrl/login'),
        body: {'phone': _normalise(phone), 'pin': _pin.text});
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to sign in.');
      }
      await UserSession.saveAuthPayload(
        (decoded['data'] as Map).cast<String, dynamic>());
      if (!mounted) {
        return;
      }
      Navigator.pushReplacement(context,
        MaterialPageRoute(builder: (_) => const HomeScreen()));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() { _phone.dispose(); _pin.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: OpFinColors.ivory,
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 40),
          const Icon(Icons.account_balance_wallet_outlined,
            size: 68, color: OpFinColors.indigo),
          const SizedBox(height: 22),
          const Text('Welcome back', textAlign: TextAlign.center,
            style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          const Text('Sign in with your phone and 6-digit PIN.',
            textAlign: TextAlign.center, style: TextStyle(color: OpFinColors.muted)),
          const SizedBox(height: 34),
          TextField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            autofillHints: const [AutofillHints.telephoneNumber],
            decoration: const InputDecoration(
              labelText: 'Phone number', hintText: '07XXXXXXXX',
              prefixIcon: Icon(Icons.phone_outlined)),
          ),
          const SizedBox(height: 18),
          TextField(
            controller: _pin,
            keyboardType: TextInputType.number,
            obscureText: true,
            maxLength: 6,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: const InputDecoration(
              labelText: '6-digit PIN', prefixIcon: Icon(Icons.lock_outline)),
            onSubmitted: (_) => _login(),
          ),
          const SizedBox(height: 16),
          SizedBox(height: 52, child: FilledButton(
            onPressed: _loading ? null : _login,
            child: _loading
              ? const CircularProgressIndicator(strokeWidth: 2)
              : const Text('Sign in'))),
          Align(alignment: Alignment.centerRight, child: TextButton(
            onPressed: () => Navigator.push(context,
              MaterialPageRoute(builder: (_) => const ForgotPasswordScreen())),
            child: const Text('Forgot PIN?'))),
          const SizedBox(height: 14),
          TextButton(
            onPressed: () => Navigator.pushReplacement(context,
              MaterialPageRoute(builder: (_) => const RegisterScreen())),
            child: const Text('Create an account')),
        ],
      ),
    ),
  );
}
