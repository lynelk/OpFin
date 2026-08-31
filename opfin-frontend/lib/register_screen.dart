import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:lottie/lottie.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/input_decoration.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/otp_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  RegisterScreenState createState() => RegisterScreenState();
}

class RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmationController = TextEditingController();

  bool _isLoading = false;
  bool _termsAccepted = false;
  bool _obscurePassword = true;

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _passwordConfirmationController.dispose();
    super.dispose();
  }

  String? _passwordError(String value) {
    if (value.length < 12) return 'Use at least 12 characters';
    if (!RegExp(r'[A-Z]').hasMatch(value)) return 'Add an uppercase letter';
    if (!RegExp(r'[a-z]').hasMatch(value)) return 'Add a lowercase letter';
    if (!RegExp(r'\d').hasMatch(value)) return 'Add a number';
    if (!RegExp(r'[^A-Za-z0-9]').hasMatch(value)) return 'Add a symbol';
    return null;
  }

  Future<void> _sendOtp() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_termsAccepted) {
      _message('Review and accept the Terms and Privacy Notice to continue.');
      return;
    }

    var phone = _phoneController.text.trim();
    phone = '256${phone.substring(1)}';

    setState(() => _isLoading = true);
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/generate-otp'),
        body: {'phone': phone},
      );
      final data = json.decode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        if (!mounted) return;
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => OtpScreen(
              phone: phone,
              name: _nameController.text.trim(),
              password: _passwordController.text,
              passwordConfirmation: _passwordConfirmationController.text,
            ),
          ),
        );
        return;
      }

      _message(data['message'] ?? 'Unable to send a verification code.');
    } catch (_) {
      _message('A network error occurred. Please try again.');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _message(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 26, vertical: 28),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  height: 116,
                  child: Lottie.asset('assets/lottie/register.json', fit: BoxFit.contain),
                ),
                const SizedBox(height: 20),
                const Text(
                  'Start your OpFin journey',
                  style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.black),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 10),
                const Text(
                  'Create a secure account first. Identity, financial profile and product permissions are completed progressively when they are actually needed.',
                  style: TextStyle(fontSize: 15, color: Colors.black54, height: 1.45),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 30),
                TextFormField(
                  controller: _nameController,
                  textCapitalization: TextCapitalization.words,
                  decoration: InputDecorations().inputStyle(
                    label: 'Full name',
                    hint: 'Your legal name',
                    icon: Icons.person_rounded,
                  ),
                  validator: (value) => (value?.trim().length ?? 0) < 3 ? 'Enter your full name' : null,
                ),
                const SizedBox(height: 18),
                TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  decoration: InputDecorations().inputStyle(
                    label: 'Mobile number',
                    hint: '07XXXXXXXX',
                    icon: Icons.phone_rounded,
                  ),
                  validator: (value) {
                    final phone = value?.trim() ?? '';
                    return RegExp(r'^0\d{9}$').hasMatch(phone) ? null : 'Enter a valid 10-digit Ugandan number starting with 0';
                  },
                ),
                const SizedBox(height: 18),
                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscurePassword,
                  decoration: InputDecorations().inputStyle(
                    label: 'Password',
                    hint: '12+ characters',
                    icon: Icons.lock_rounded,
                  ).copyWith(
                    suffixIcon: IconButton(
                      onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                      icon: Icon(_obscurePassword ? Icons.visibility_rounded : Icons.visibility_off_rounded),
                    ),
                  ),
                  validator: (value) => _passwordError(value ?? ''),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Use 12+ characters with uppercase, lowercase, a number and a symbol.',
                  style: TextStyle(fontSize: 12, color: Colors.black54),
                ),
                const SizedBox(height: 18),
                TextFormField(
                  controller: _passwordConfirmationController,
                  obscureText: _obscurePassword,
                  decoration: InputDecorations().inputStyle(
                    label: 'Confirm password',
                    hint: 'Repeat your password',
                    icon: Icons.lock_outline_rounded,
                  ),
                  validator: (value) => value != _passwordController.text ? 'Passwords do not match' : null,
                ),
                const SizedBox(height: 18),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  value: _termsAccepted,
                  onChanged: (value) => setState(() => _termsAccepted = value ?? false),
                  title: const Text(
                    'I agree to the Terms and Privacy Notice. Product-specific data permissions will be requested separately when required.',
                    style: TextStyle(fontSize: 13, height: 1.4),
                  ),
                ),
                const SizedBox(height: 22),
                SizedBox(
                  height: 52,
                  child: ElevatedButton(
                    onPressed: _isLoading ? null : _sendOtp,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.black,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: _isLoading
                        ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Verify phone and continue', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ),
                const SizedBox(height: 18),
                TextButton(
                  onPressed: () => Navigator.of(context).pushReplacement(
                    MaterialPageRoute(builder: (context) => const LoginScreen()),
                  ),
                  child: const Text('Already have an account? Sign in'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
