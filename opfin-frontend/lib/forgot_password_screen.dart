import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/input_decoration.dart';
import 'package:opfin/otp_screen.dart';
import 'package:http/http.dart' as http;

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final TextEditingController _phoneController = TextEditingController();

  final _passwordController = TextEditingController();

  final _passwordConfirmationController = TextEditingController();

  bool _isLoading = false;

  Future<void> _sendOtp() async {
    String phone = _phoneController.text.trim();

    final password = _passwordController.text.trim();
    final passwordConfirmation = _passwordConfirmationController.text.trim();
    if (phone.isEmpty || password.isEmpty || passwordConfirmation.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill in all fields')),
      );
      return;
    }
    if (!RegExp(r'^0\d{9}$').hasMatch(phone)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Enter a valid 10-digit phone number starting with 0')),
      );
      return;
    }
    if (password.length < 8) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Password must be at least 8 characters long')),
      );
      return;
    }
    if (password != passwordConfirmation) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Password and confirmation do not match')),
      );
      return;
    }
    phone = '256${phone.substring(1)}';
    setState(() {
      _isLoading = true;
    });
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/generate-otp'),
        body: {'phone': phone},
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          // Navigate to OTP Screen
          if (!mounted) return;
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => OtpScreen(
                phone: phone,
                password: _passwordController.text.trim(),
                passwordConfirmation:
                    _passwordConfirmationController.text.trim(),
              ),
            ),
          );
        } else {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message'] ?? 'Failed to send OTP')),
          );
        }
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('An error occurred. Try again later.')),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('A network error occurred. Please try again.')),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: SingleChildScrollView(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 26.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                const SizedBox(height: 40),

                // Animation
                SizedBox(
                  height: 140,
                  child: Lottie.asset(
                    'assets/lottie/forgot_password.json',
                    fit: BoxFit.contain,
                  ),
                ),

                const SizedBox(height: 30),

                // Title
                const Text(
                  "Forgot Password",
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    color: Colors.black,
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 8),

                const Text(
                  "Enter your phone number and new password to receive an OTP.",
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.black54,
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 40),

                // Phone Input
                TextField(
                  controller: _phoneController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecorations().inputStyle(
                    label: "Phone Number",
                    hint: "0700460055",
                    icon: Icons.phone_rounded,
                  ),
                  style: const TextStyle(color: Colors.black),
                ),

                const SizedBox(height: 25),

                // New Password
                TextField(
                  controller: _passwordController,
                  obscureText: true,
                  decoration: InputDecorations().inputStyle(
                    label: "New Password",
                    hint: "•••••••••••",
                    icon: Icons.lock_rounded,
                  ),
                  style: const TextStyle(color: Colors.black),
                ),

                const SizedBox(height: 25),

                // Confirm New Password
                TextField(
                  controller: _passwordConfirmationController,
                  obscureText: true,
                  decoration: InputDecorations().inputStyle(
                    label: "Confirm New Password",
                    hint: "•••••••••••",
                    icon: Icons.lock_rounded,
                  ),
                  style: const TextStyle(color: Colors.black),
                ),

                const SizedBox(height: 35),

                // Reset Button
                _isLoading
                    ? const CircularProgressIndicator(color: Colors.black)
                    : SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _sendOtp,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.black,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            textStyle: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          child: const Text("Reset Password"),
                        ),
                      ),

                const SizedBox(height: 30),

                Row(
                  children: [
                    Expanded(child: Divider(color: Colors.black26)),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 10),
                      child: Text(
                        "OR",
                        style: TextStyle(
                          color: Colors.black45,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    Expanded(child: Divider(color: Colors.black26)),
                  ],
                ),

                const SizedBox(height: 30),
                GestureDetector(
                  onTap: () => Navigator.pop(context),
                  child: RichText(
                    text: const TextSpan(
                      text: "Remembered your password? ",
                      style: TextStyle(
                        color: Colors.black87,
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                      ),
                      children: [
                        TextSpan(
                          text: "Login",
                          style: TextStyle(
                            color: Colors.black,
                            fontWeight: FontWeight.w800,
                            decoration: TextDecoration.underline,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 40),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
