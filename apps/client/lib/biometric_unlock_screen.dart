import 'package:flutter/material.dart';
import 'package:opfin/home_screen.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/services/device_biometrics.dart';
import 'package:opfin/services/user_session.dart';

/// Shown only when an already authenticated person opted in on this device.
/// A successful native prompt unlocks the local app view, never a new API session.
class BiometricUnlockScreen extends StatefulWidget {
  const BiometricUnlockScreen({super.key});

  @override
  State<BiometricUnlockScreen> createState() => _BiometricUnlockScreenState();
}

class _BiometricUnlockScreenState extends State<BiometricUnlockScreen> {
  final _biometrics = DeviceBiometrics();
  bool _checking = false;
  bool _attempted = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _unlock());
  }

  Future<void> _unlock() async {
    if (_checking || !mounted) return;
    setState(() {
      _checking = true;
      _attempted = true;
    });
    try {
      if (!(await _biometrics.isEnabledForCurrentUser())) {
        await _usePin();
        return;
      }
      final verified = await _biometrics.verify(reason: 'Unlock your OpFin financial life');
      final token = await UserSession.getAccessToken();
      if (verified && token != null && token.isNotEmpty && mounted) {
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const HomeScreen()));
      }
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<void> _usePin() async {
    // Biometric denial and OS lockout must never reveal the Home screen.
    // PIN sign-in creates a fresh server-authenticated session.
    await UserSession.clear();
    if (!mounted) return;
    Navigator.pushAndRemoveUntil(
      context, MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Unlock OpFin')),
        body: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 360),
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.fingerprint, size: 64),
                    const SizedBox(height: 16),
                    const Text('Your financial life stays private',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 12),
                    Text(
                      _checking
                          ? 'Confirm your identity using your device.'
                          : _attempted
                              ? 'Biometric confirmation was not completed. Try again or sign in with your OpFin PIN.'
                              : 'Unlock using enrolled device biometrics.',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 24),
                    if (_checking)
                      const CircularProgressIndicator()
                    else
                      FilledButton.icon(
                        onPressed: _unlock,
                        icon: const Icon(Icons.fingerprint),
                        label: const Text('Try biometrics again'),
                      ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: _usePin,
                      child: const Text('Use OpFin PIN instead'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
}
