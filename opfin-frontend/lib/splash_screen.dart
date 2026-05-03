import 'dart:async';

import 'package:flutter/material.dart';
import 'package:opfin/home_screen.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/onboarding_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  SplashScreenState createState() => SplashScreenState();
}

class SplashScreenState extends State<SplashScreen> {
  double _logoOpacity = 0.0;
  double _taglineOpacity = 0.0;

  @override
  void initState() {
    super.initState();
    _startAnimation();
  }

  void _startAnimation() {
    Timer(const Duration(milliseconds: 500), () {
      setState(() {
        _logoOpacity = 1.0;
      });

      Timer(const Duration(milliseconds: 1000), () {
        setState(() {
          _taglineOpacity = 1.0;
        });

        Timer(const Duration(milliseconds: 2000), () {
          _navigateToHome();
        });
      });
    });
  }

  void _navigateToHome() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    if (prefs.getBool("seenOnboarding") == true) {
      if (prefs.getInt('user_id') == null) {
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        );
      } else {
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (context) => const HomeScreen()),
        );
      }
    } else {
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const OnboardingScreen()),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            AnimatedOpacity(
              opacity: _logoOpacity,
              duration: const Duration(milliseconds: 1000),
              child: const Text(
                'OpFin',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 48,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            const SizedBox(height: 20),
            AnimatedOpacity(
              opacity: _taglineOpacity,
              duration: const Duration(milliseconds: 1000),
              child: const Text(
                'Empowering Your Finances',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
