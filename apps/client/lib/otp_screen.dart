import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:lottie/lottie.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/input_decoration.dart';
import 'package:opfin/login_screen.dart';

class OtpScreen extends StatefulWidget {
  final String phone;
  final String? name;
  final String password;
  final String passwordConfirmation;

  const OtpScreen({
    super.key,
    required this.phone,
    this.name,
    required this.password,
    required this.passwordConfirmation,
  });

  @override
  OtpScreenState createState() => OtpScreenState();
}

class OtpScreenState extends State<OtpScreen> {
  final TextEditingController _otpController = TextEditingController();
  bool _isResendEnabled = false;
  int _countdown = 300;
  bool _isLoading = false;

  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void dispose() {
    _otpController.dispose();
    super.dispose();
  }

  void _startCountdown() {
    Future.delayed(const Duration(seconds: 1), () {
      if (!mounted) return;
      if (_countdown > 0) {
        setState(() => _countdown--);
        _startCountdown();
      } else {
        setState(() => _isResendEnabled = true);
      }
    });
  }

  Future<void> _verifyOtp() async {
    setState(() => _isLoading = true);
    try {
      final otp = _otpController.text.trim();
      final response = await http.post(
        Uri.parse('$apiUrl/verify-otp'),
        body: {'phone': widget.phone, 'otp': otp},
      );
      final data = json.decode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        if (widget.name != null) {
          final verificationToken = data['data']?['verification_token'] as String?;
          if (verificationToken == null || verificationToken.isEmpty) {
            _showMessage('Phone verification could not be completed. Request a new code.');
            return;
          }
          await _register(verificationToken);
        } else {
          await _resetPassword();
        }
        return;
      }

      _showMessage(data['message'] ?? 'OTP verification failed');
    } catch (_) {
      _showMessage('A network error occurred. Please try again.');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _regenerateOtp() async {
    setState(() => _isLoading = true);
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/generate-otp'),
        body: {'phone': widget.phone},
      );
      final data = json.decode(response.body);
      _showMessage(data['message'] ?? (response.statusCode == 200 ? 'A new code was sent.' : 'Unable to send a new code.'));
    } catch (_) {
      _showMessage('A network error occurred. Please try again.');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _resendOtp() {
    if (!_isResendEnabled) return;
    _regenerateOtp();
    setState(() {
      _isResendEnabled = false;
      _countdown = 300;
      _otpController.clear();
    });
    _startCountdown();
  }

  Future<void> _register(String verificationToken) async {
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/register'),
        body: {
          'name': widget.name,
          'phone': widget.phone,
          'verification_token': verificationToken,
          'password': widget.password,
          'password_confirmation': widget.passwordConfirmation,
        },
      );
      final data = json.decode(response.body);

      if ((response.statusCode == 200 || response.statusCode == 201) && data['success'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Account created. Sign in to continue your OpFin setup.')),
        );
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        );
        return;
      }

      _showMessage(data['message'] ?? 'Registration failed');
    } catch (_) {
      _showMessage('A network error occurred. Please try again.');
    }
  }

  Future<void> _resetPassword() async {
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/reset-password'),
        body: {
          'phone': widget.phone,
          'otp': _otpController.text.trim(),
          'password': widget.password,
          'password_confirmation': widget.passwordConfirmation,
        },
      );
      final data = json.decode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Password reset successfully.')),
        );
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        );
        return;
      }

      _showMessage(data['message'] ?? 'Password reset failed');
    } catch (_) {
      _showMessage('A network error occurred. Please try again.');
    }
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: SingleChildScrollView(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 26.0),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const SizedBox(height: 50),
                  SizedBox(
                    height: 160,
                    child: Lottie.asset('assets/lottie/otp.json', fit: BoxFit.contain),
                  ),
                  const SizedBox(height: 25),
                  const Text(
                    'Verify your phone',
                    style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.black),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'We sent a 6-digit code to\n${widget.phone}. Enter it below to continue securely.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 16, color: Colors.black54),
                  ),
                  const SizedBox(height: 40),
                  TextFormField(
                    controller: _otpController,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    decoration: InputDecorations().inputStyle(
                      label: 'Verification code',
                      hint: '6 digits',
                      icon: Icons.lock_rounded,
                    ),
                    style: const TextStyle(color: Colors.black),
                    validator: (value) {
                      final code = value?.trim() ?? '';
                      if (!RegExp(r'^\d{6}$').hasMatch(code)) {
                        return 'Enter the 6-digit code';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 18),
                  Text(
                    _countdown > 0 ? 'Code expires in $_countdown seconds' : 'This code has expired',
                    style: TextStyle(
                      fontSize: 14,
                      color: _countdown > 0 ? Colors.black54 : Colors.red,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 30),
                  _isLoading
                      ? const CircularProgressIndicator(color: Colors.black)
                      : SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: _countdown > 0
                                ? () {
                                    if (_formKey.currentState!.validate()) _verifyOtp();
                                  }
                                : null,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.black,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: const Text('Verify and continue'),
                          ),
                        ),
                  const SizedBox(height: 24),
                  if (_isResendEnabled)
                    TextButton(
                      onPressed: _resendOtp,
                      child: const Text('Send a new code'),
                    ),
                  const SizedBox(height: 40),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
