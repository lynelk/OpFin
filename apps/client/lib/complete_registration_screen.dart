import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/home_screen.dart';
import 'package:opfin/services/user_session.dart';
import 'package:opfin/widgets/auth_scaffold.dart';

class CompleteRegistrationScreen extends StatefulWidget {
  const CompleteRegistrationScreen({
    super.key,
    required this.phone,
    required this.verificationToken,
  });

  final String phone;
  final String verificationToken;

  @override
  State<CompleteRegistrationScreen> createState() =>
      _CompleteRegistrationScreenState();
}

class _CompleteRegistrationScreenState
    extends State<CompleteRegistrationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _first = TextEditingController();
  final _other = TextEditingController();
  final _last = TextEditingController();
  final _pin = TextEditingController();
  final _confirm = TextEditingController();
  bool _loading = false;

  bool _weakPin(String pin) {
    const weak = {
      '012345','123456','234567','345678','456789',
      '987654','876543','765432','654321','543210',
    };
    return RegExp(r'^(\d)\1{5}$').hasMatch(pin) || weak.contains(pin);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    try {
      final response = await http.post(
        Uri.parse('$apiUrl/register'),
        body: {
          'phone': widget.phone,
          'verification_token': widget.verificationToken,
          'first_name': _first.text.trim(),
          'other_name': _other.text.trim(),
          'last_name': _last.text.trim(),
          'pin': _pin.text,
          'pin_confirmation': _confirm.text,
          'terms_accepted': '1',
          'preferred_language': 'en',
        },
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode < 200 ||
          response.statusCode >= 300 ||
          decoded['success'] != true) {
        throw Exception(decoded['message']?.toString() ?? 'Unable to create account.');
      }
      await UserSession.saveAuthPayload(
        (decoded['data'] as Map).cast<String, dynamic>(),
      );
      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomeScreen()),
        (_) => false,
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _first.dispose(); _other.dispose(); _last.dispose();
    _pin.dispose(); _confirm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => OpFinAuthScaffold(
        eyebrow: 'Create account',
        title: 'Tell us your name',
        description:
            'Use the names on your National ID. Your other name is optional. Then create the 6-digit PIN you will use to sign in.',
        showBackButton: true,
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextFormField(
                controller: _first,
                textCapitalization: TextCapitalization.words,
                autofillHints: const [AutofillHints.givenName],
                decoration: const InputDecoration(
                  labelText: 'First name',
                  prefixIcon: Icon(Icons.person_outline),
                ),
                validator: (v) =>
                    (v?.trim().isEmpty ?? true) ? 'Enter first name' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _other,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(
                  labelText: 'Other name (optional)',
                  prefixIcon: Icon(Icons.person_outline),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _last,
                textCapitalization: TextCapitalization.words,
                autofillHints: const [AutofillHints.familyName],
                decoration: const InputDecoration(
                  labelText: 'Last name',
                  prefixIcon: Icon(Icons.person_outline),
                ),
                validator: (v) =>
                    (v?.trim().isEmpty ?? true) ? 'Enter last name' : null,
              ),
              const SizedBox(height: 28),
              const Text(
                'Create a 6-digit PIN',
                style: TextStyle(
                  color: OpFinColors.ink,
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Use this PIN to sign in. Never share it with anyone, including OpFin staff.',
                style: TextStyle(color: OpFinColors.muted),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _pin,
                obscureText: true,
                keyboardType: TextInputType.number,
                maxLength: 6,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                autofillHints: const [AutofillHints.newPassword],
                decoration: const InputDecoration(
                  labelText: '6-digit PIN',
                  prefixIcon: Icon(Icons.lock_outline),
                ),
                validator: (value) {
                  final pin = value ?? '';
                  if (!RegExp(r'^\d{6}$').hasMatch(pin)) return 'Enter 6 digits';
                  if (_weakPin(pin)) return 'Choose a less predictable PIN';
                  return null;
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _confirm,
                obscureText: true,
                keyboardType: TextInputType.number,
                maxLength: 6,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                autofillHints: const [AutofillHints.newPassword],
                decoration: const InputDecoration(
                  labelText: 'Confirm PIN',
                  prefixIcon: Icon(Icons.lock_outline),
                ),
                validator: (v) => v != _pin.text ? 'PINs do not match' : null,
              ),
              const SizedBox(height: 22),
              SizedBox(
                height: 52,
                child: FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Create my account'),
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'Someone you trust may help enter information. Keep your PIN and OTP private.',
                style: TextStyle(color: OpFinColors.muted),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      );
}
