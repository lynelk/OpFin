import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/input_decoration.dart';
import 'package:opfin/login_screen.dart';
import 'package:http/http.dart' as http;

class OtpScreen extends StatefulWidget {
  final String phone;
  final String? name;
  final String password;
  final String passwordConfirmation;
  final String otp;

  const OtpScreen(
      {super.key,
      required this.phone,
      this.name,
      required this.password,
      required this.passwordConfirmation,
      required this.otp}); // The phone number will be passed from the registration page

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

  void _startCountdown() {
    Future.delayed(const Duration(seconds: 1), () {
      if (_countdown > 0) {
        setState(() {
          _countdown--;
        });
        _startCountdown();
      } else {
        setState(() {
          _isResendEnabled = true;
        });
      }
    });
  }

  Future<void> _verifyOtp() async {
    setState(() {
      _isLoading = true;
    });
    try {
      final otp = _otpController.text.trim();
      final response = await http.post(
        Uri.parse('$apiUrl/verify-otp'),
        body: {
          'phone': widget.phone,
          'otp': otp,
        },
      );
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          // Do NOT set _isLoading = false here, let navigation happen with loader showing
          if (widget.name != null) {
            await _register();
          } else {
            await _resetPassword();
          }
          return; // Exit so finally does not run
        } else {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
                content: Text(data['message'] ?? 'OTP verification failed')),
          );
        }
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('An error occurred. Try again later.')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Network error: ${e.toString()}')),
      );
    } finally {
      if (mounted && !_isLoading) {
        // Only set _isLoading to false if not navigating away
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _regenerateOTP() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final response = await http.post(
        Uri.parse('$apiUrl/generate-otp'), // Replace with your API endpoint
        body: {'phone': widget.phone},
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(data['message'])),
        );
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('An error occurred. Try again later.')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Network error: ${e.toString()}')),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _resendOtp() {
    if (_isResendEnabled) {
      // Trigger logic to resend OTP (e.g., call your API to send a new OTP)
      _regenerateOTP();
      setState(() {
        _isResendEnabled = false;
        _countdown = 300;
      });
      _startCountdown();
    }
  }

  Future<void> _register() async {
    // Navigate to OTP Screen
    setState(() {
      _isLoading = true;
    });

    try {
      final response = await http.post(
        Uri.parse('$apiUrl/register'), // Replace with your API endpoint
        body: {
          'name': widget.name,
          'phone': widget.phone,
          'password': widget.password,
          'password_confirmation': widget.passwordConfirmation
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          if (!mounted) return;
          // Show success message and navigate to login
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Registration successful')),
          );
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(builder: (context) => const LoginScreen()),
          );
        } else {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message'] ?? 'Registration failed')),
          );
        }
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('An error occurred. Try again later.')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Network error: ${e.toString()}')),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _resetPassword() async {
    // Navigate to OTP Screen
    setState(() {
      _isLoading = true;
    });
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/reset-password'), // Replace with your API endpoint
        body: {
          'phone': widget.phone,
          'password': widget.password,
          'password_confirmation': widget.passwordConfirmation
        },
      );
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          // Show success message and navigate to login
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
                content: Text('Password reset successfully successful')),
          );
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(builder: (context) => const LoginScreen()),
          );
        } else {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message'] ?? 'Registration failed')),
          );
        }
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('An error occurred. Try again later.')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Network error: ${e.toString()}')),
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
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const SizedBox(height: 50),

                  // OTP Animation
                  SizedBox(
                    height: 160,
                    child: Lottie.asset(
                      'assets/lottie/otp.json',
                      fit: BoxFit.contain,
                    ),
                  ),

                  const SizedBox(height: 25),

                  // Title
                  const Text(
                    "Enter OTP",
                    style: TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.w800,
                      color: Colors.black,
                    ),
                    textAlign: TextAlign.center,
                  ),

                  const SizedBox(height: 12),

                  // Subtitle / instructions
                  Text(
                    "We sent a One-Time Password to\n${widget.phone}. Enter it below to verify your account.",
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 16,
                      color: Colors.black54,
                    ),
                  ),

                  const SizedBox(height: 40),

                  // OTP Field
                  TextFormField(
                    controller: _otpController,
                    keyboardType: TextInputType.number,
                    decoration: InputDecorations().inputStyle(
                      label: "Enter OTP",
                      hint: "e.g. ${widget.otp}",
                      icon: Icons.lock_rounded,
                    ),
                    style: const TextStyle(color: Colors.black),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please enter the OTP';
                      }
                      if (value.length < 4 || value.length > 6) {
                        return 'OTP should be 4-6 digits';
                      }
                      if (!RegExp(r'^\d+$').hasMatch(value)) {
                        return 'OTP must be numbers only';
                      }
                      return null;
                    },
                  ),

                  const SizedBox(height: 25),

                  // Countdown
                  Text(
                    "OTP expires in $_countdown seconds",
                    style: const TextStyle(
                      fontSize: 14,
                      color: Colors.red,
                      fontWeight: FontWeight.w600,
                    ),
                  ),

                  const SizedBox(height: 35),

                  // Verify Button
                  _isLoading
                      ? const CircularProgressIndicator(color: Colors.black)
                      : SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: () {
                              if (_formKey.currentState!.validate()) {
                                _verifyOtp();
                              }
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.black,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            child: const Text("Verify OTP"),
                          ),
                        ),

                  const SizedBox(height: 25),

                  // Resend OTP
                  if (_isResendEnabled)
                    GestureDetector(
                      onTap: _resendOtp,
                      child: const Text(
                        "Resend OTP",
                        style: TextStyle(
                          color: Colors.black,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          decoration: TextDecoration.underline,
                        ),
                      ),
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
