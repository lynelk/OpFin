import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:lottie/lottie.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/input_decoration.dart';
import 'dart:convert';

import 'package:opfin/login_screen.dart';
import 'package:opfin/otp_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  RegisterScreenState createState() => RegisterScreenState();
}

class RegisterScreenState extends State<RegisterScreen> {
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmationController = TextEditingController();
  bool _isLoading = false;
  bool _termsAccepted = false;

  Future<void> _sendOtp() async {
    final name = _nameController.text.trim();
    var phone = _phoneController.text.trim();
    final password = _passwordController.text.trim();
    final passwordConfirmation = _passwordConfirmationController.text.trim();

    if (name.isEmpty ||
        phone.isEmpty ||
        password.isEmpty ||
        passwordConfirmation.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill in all fields')),
      );
      return;
    }
    if (password.length < 8 || passwordConfirmation.length < 8) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('A password should be atleast 8 characters')),
      );
      return;
    }

    if (password != passwordConfirmation) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Confirmed password should be equal to password')),
      );
      return;
    }

    phone = phone.replaceFirst('0', '256');

    setState(() {
      _isLoading = true;
    });

    try {
      final response = await http.post(
        Uri.parse('$apiUrl/generate-otp'), // Replace with your API endpoint
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
                name: _nameController.text.trim(),
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

                // Lottie Animation
                SizedBox(
                  height: 140,
                  child: Lottie.asset(
                    'assets/lottie/register.json',
                    fit: BoxFit.contain,
                  ),
                ),

                const SizedBox(height: 30),

                // Title
                const Text(
                  "Create Your Account",
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    color: Colors.black,
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 8),

                const Text(
                  "Join and start accessing your salary advances or short loans.",
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.black54,
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 40),

                // Name
                TextField(
                  controller: _nameController,
                  decoration: InputDecorations().inputStyle(
                    label: 'Full Name',
                    hint: 'John Doe',
                    icon: Icons.person_rounded,
                  ),
                  style: const TextStyle(color: Colors.black),
                ),

                const SizedBox(height: 25),

                // Phone
                TextField(
                  controller: _phoneController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecorations().inputStyle(
                    label: 'Phone Number',
                    hint: '0700460055',
                    icon: Icons.phone_rounded,
                  ),
                  style: const TextStyle(color: Colors.black),
                ),

                const SizedBox(height: 25),

                // Password
                TextField(
                  controller: _passwordController,
                  obscureText: true,
                  decoration: InputDecorations().inputStyle(
                    label: 'Password',
                    hint: '•••••••••••',
                    icon: Icons.lock_rounded,
                  ),
                  style: const TextStyle(color: Colors.black),
                ),

                const SizedBox(height: 25),

                // Confirm Password
                TextField(
                  controller: _passwordConfirmationController,
                  obscureText: true,
                  decoration: InputDecorations().inputStyle(
                    label: 'Confirm Password',
                    hint: '•••••••••••',
                    icon: Icons.lock_rounded,
                  ),
                  style: const TextStyle(color: Colors.black),
                ),

                const SizedBox(height: 20),

                // Terms Checkbox
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Checkbox(
                      value: _termsAccepted,
                      activeColor: Colors.black,
                      onChanged: (value) {
                        setState(() => _termsAccepted = value ?? false);
                      },
                    ),
                    const Expanded(
                      child: Text(
                        "I agree to the Terms and Conditions and confirm that the provided information is correct.",
                        style: TextStyle(
                          color: Colors.black87,
                          fontSize: 14,
                          height: 1.4,
                        ),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 35),

                // Register Button
                _isLoading
                    ? const CircularProgressIndicator(color: Colors.black)
                    : SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _termsAccepted ? _sendOtp : null,
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
                          child: const Text("Register"),
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

                // Already have account?
                GestureDetector(
                  onTap: () {
                    Navigator.pushReplacement(
                      context,
                      MaterialPageRoute(builder: (_) => const LoginScreen()),
                    );
                  },
                  child: RichText(
                    textAlign: TextAlign.center,
                    text: const TextSpan(
                      text: "Already have an account? ",
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
