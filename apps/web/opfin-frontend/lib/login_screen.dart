import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/forgot_password_screen.dart';
import 'package:opfin/home_screen.dart';
import 'package:opfin/input_decoration.dart';
import 'package:opfin/register_screen.dart';
import 'package:opfin/services/user_session.dart';
import 'package:http/http.dart' as http;

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  bool _isLoading = false;

  Future<void> _login() async {
    var phone = _phoneController.text.trim();
    final password = _passwordController.text.trim();

    if (phone.isEmpty || password.isEmpty) {
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
    phone = '256${phone.substring(1)}';

    setState(() {
      _isLoading = true;
    });

    try {
      final response = await http.post(
        Uri.parse('$apiUrl/login'),
        body: {'phone': phone, 'password': password},
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          final userId = data['user']['id'];
          final name = data['user']['name'];
          final role = data['user']['role'];
          final userPhone = data['user']['phone'];
          final nationalId = data['user']['national_id'] ?? "";
          final dateOfBirth = data['user']['date_of_birth'] ?? "";
          final ninStatus = data['user']['nin_status'] ?? "";
          final accessToken = data['access_token'];

          if (data['credit_score'] == null) {
            data['credit_score'] = {
              "score": 0,
              "band": "Unknown",
              "rating": "Unknown",
              "probability_of_default_percent": 0.0
            };
          }
          final score = data['credit_score']['score'];
          final band = data['credit_score']['band'];
          final rating = data['credit_score']['rating'];
          final defaultingPercentage =
              data['credit_score']['probability_of_default_percent'];

          await UserSession.saveSession(
            userId: userId,
            accessToken: accessToken,
            name: name,
            role: role,
            phone: userPhone,
            nationalId: nationalId,
            dateOfBirth: dateOfBirth,
            ninStatus: ninStatus,
            creditScore: score,
            creditBand: band,
            creditRating: rating,
            defaultingPercentage: (defaultingPercentage as num).toDouble(),
          );
          if (!mounted) return;
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (context) => const HomeScreen()),
          );
        } else {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message'] ?? 'Login failed')),
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
              children: [
                const SizedBox(height: 40),

                // App Icon or Animation
                SizedBox(
                  height: 140,
                  child: Lottie.asset(
                    'assets/lottie/login.json',
                    fit: BoxFit.contain,
                  ),
                ),

                const SizedBox(height: 30),

                // Title
                const Text(
                  "Welcome Back",
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    color: Colors.black,
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 8),

                const Text(
                  "Login to continue accessing your loan services.",
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

                // Password Input
                TextField(
                  controller: _passwordController,
                  obscureText: true,
                  decoration: InputDecorations().inputStyle(
                    label: "Password",
                    hint: "•••••••••••",
                    icon: Icons.lock_rounded,
                  ),
                  style: const TextStyle(color: Colors.black),
                ),

                const SizedBox(height: 35),

                // Login Button
                _isLoading
                    ? const CircularProgressIndicator(color: Colors.black)
                    : SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _login,
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
                          child: const Text("Login"),
                        ),
                      ),
                const SizedBox(height: 20),

                // Forgot Password (right aligned, subtle)
                Align(
                  alignment: Alignment.centerRight,
                  child: GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (_) => const ForgotPasswordScreen()),
                      );
                    },
                    child: const Text(
                      "Forgot password?",
                      style: TextStyle(
                        color: Colors.black54,
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
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
                  onTap: () {
                    Navigator.pushReplacement(
                      context,
                      MaterialPageRoute(builder: (_) => const RegisterScreen()),
                    );
                  },
                  child: RichText(
                    textAlign: TextAlign.center,
                    text: const TextSpan(
                      text: "Don’t have an account? ",
                      style: TextStyle(
                        color: Colors.black87,
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                      ),
                      children: [
                        TextSpan(
                          text: "Register",
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
