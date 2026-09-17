import 'dart:async';
import 'package:flutter/material.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/brand/opfin_theme.dart';
import 'package:opfin/home_screen.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/onboarding_screen.dart';
import 'package:opfin/services/user_session.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  SplashScreenState createState() => SplashScreenState();
}

class SplashScreenState extends State<SplashScreen> {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer(const Duration(milliseconds: 600), _navigateToHome);
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _navigateToHome() async {
    Widget destination = const LoginScreen();
    try {
      final prefs = await SharedPreferences.getInstance();
      if (prefs.getBool('seenOnboarding') != true) {
        destination = const OnboardingScreen();
      } else {
        final userId = await UserSession.getUserId();
        final token = await UserSession.getAccessToken();
        if (userId != null && token != null && token.isNotEmpty) {
          destination = const HomeScreen();
        }
      }
    } catch (_) {
      // A local-storage failure requires sign-in; it must not fabricate a session.
      destination = const LoginScreen();
    }
    if (!mounted) return;
    Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => destination));
  }

  @override
  Widget build(BuildContext context) => const Scaffold(
    backgroundColor: OpFinColors.ivory,
    body: SafeArea(
      child: Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              OpFinSymbol(size: 112),
              SizedBox(height: 24),
              Text('OpFin', style: TextStyle(fontFamily: 'Inter', color: OpFinColors.indigo, fontSize: 36, fontWeight: FontWeight.w700)),
              SizedBox(height: 12),
              Text('Your next step, clearer.', textAlign: TextAlign.center, style: TextStyle(fontFamily: 'Inter', color: OpFinColors.ink, fontSize: 16, height: 1.5)),
              SizedBox(height: 32),
              SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, semanticsLabel: 'Opening OpFin')),
            ],
          ),
        ),
      ),
    ),
  );
}
