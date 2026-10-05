import 'dart:convert';
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:opfin/services/distribution_channel.dart';
import 'package:opfin/services/opfin_http.dart';
import 'package:opfin/complete_registration_screen.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/widgets/auth_scaffold.dart';
import 'package:sms_autofill/sms_autofill.dart';

class OtpScreen extends StatefulWidget {
  const OtpScreen({
    super.key,
    required this.phone,
    this.registration = false,
    this.resetPin,
  });

  final String phone;
  final bool registration;
  final String? resetPin;

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  final _controller = TextEditingController();
  StreamSubscription<String>? _smsSubscription;
  SmsAutoFill? _sms;

  bool _loading = false;
  bool _resend = false;
  bool _autoSubmitted = false;
  int _countdown = 300;

  @override
  void initState() {
    super.initState();
    unawaited(_listenForCodeSafely());
    _tick();
  }

  Future<void> _listenForCodeSafely() async {
    if (kIsWeb ||
        defaultTargetPlatform != TargetPlatform.android ||
        resolveDistributionChannel() == 'huawei_appgallery') {
      return;
    }
    try {
      await _smsSubscription?.cancel();
      if (!mounted) return;
      final sms = _sms ??= SmsAutoFill();
      _smsSubscription = sms.code.listen((value) {
        if (!mounted || !RegExp(r'^\d{6}$').hasMatch(value)) return;
        _controller.text = value;
        if (!_autoSubmitted && !_loading) {
          _autoSubmitted = true;
          unawaited(_verify());
        }
      }, onError: (Object _) {
        // Manual input stays available when optional native autofill fails.
      });
      await sms.listenForCode();
    } catch (_) {
      // A missing Google SMS Retriever service is not an authentication failure.
    }
  }

  Future<void> _disposeAutofill() async {
    try {
      await _smsSubscription?.cancel();
      await _sms?.unregisterListener();
    } catch (_) {
      // Disposing optional autofill must not affect the account/session flow.
    }
  }

  void _tick() {
    Future.delayed(const Duration(seconds: 1), () {
      if (!mounted) {
        return;
      }

      if (_countdown > 0) {
        setState(() => _countdown--);
        _tick();
      } else {
        setState(() => _resend = true);
      }
    });
  }

  Future<void> _verify() async {
    final otp = _controller.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(otp) || _loading) {
      return;
    }

    setState(() => _loading = true);

    try {
      final response = await OpFinHttp.post(
        Uri.parse('$apiUrl/verify-otp'),
        body: {'phone': widget.phone, 'otp': otp},
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;

      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(
          decoded['message']?.toString() ?? 'The code did not work.',
        );
      }

      if (widget.registration) {
        final token =
            ((decoded['data'] as Map?)?['verification_token'])?.toString();
        if (token == null || token.isEmpty) {
          throw Exception('Phone verification failed.');
        }
        if (!mounted) {
          return;
        }

        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => CompleteRegistrationScreen(
              phone: widget.phone,
              verificationToken: token,
            ),
          ),
        );
        return;
      }

      if (widget.resetPin != null) {
        final reset = await OpFinHttp.post(
          Uri.parse('$apiUrl/reset-password'),
          body: {
            'phone': widget.phone,
            'otp': otp,
            'pin': widget.resetPin,
            'pin_confirmation': widget.resetPin,
          },
        );
        final body = jsonDecode(reset.body) as Map<String, dynamic>;

        if (reset.statusCode != 200 || body['success'] != true) {
          throw Exception(
            body['message']?.toString() ?? 'Unable to reset PIN.',
          );
        }
        if (!mounted) {
          return;
        }

        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Your new PIN is ready.')),
        );
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
          (_) => false,
        );
      }
    } catch (error) {
      _autoSubmitted = false;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(error.toString().replaceFirst('Exception: ', '')),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _sendAgain() async {
    setState(() => _loading = true);

    try {
      final response = await OpFinHttp.post(
        Uri.parse('$apiUrl/generate-otp'),
        body: {'phone': widget.phone},
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;

      if (response.statusCode != 200 || decoded['success'] != true) {
        throw Exception(
          decoded['message']?.toString() ?? 'Unable to send code.',
        );
      }

      if (!mounted) return;
      setState(() {
        _countdown = 300;
        _resend = false;
        _controller.clear();
        _autoSubmitted = false;
      });
      unawaited(_listenForCodeSafely());
      _tick();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(error.toString().replaceFirst('Exception: ', '')),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  void dispose() {
    unawaited(_disposeAutofill());
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => OpFinAuthScaffold(
        eyebrow: widget.registration ? 'Create account' : 'Secure verification',
        title: 'Enter the 6-digit code',
        description: 'We sent it to ' +
            widget.phone +
            '. On supported phones, OpFin fills it in automatically.',
        showBackButton: true,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _controller,
              keyboardType: TextInputType.number,
              maxLength: 6,
              autofillHints: const [AutofillHints.oneTimeCode],
              decoration: const InputDecoration(
                labelText: 'Verification code',
                hintText: '6 digits',
                prefixIcon: Icon(Icons.sms_outlined),
                border: OutlineInputBorder(),
              ),
              onChanged: (value) {
                if (value.length == 6 && !_autoSubmitted) {
                  _autoSubmitted = true;
                  _verify();
                }
              },
            ),
            const SizedBox(height: 12),
            Text(
              _countdown > 0
                  ? 'Code expires in ' + _countdown.toString() + ' seconds'
                  : 'Code expired',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 22),
            SizedBox(
              height: 52,
              child: FilledButton(
                onPressed: _loading || _countdown <= 0 ? null : _verify,
                child: _loading
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Continue'),
              ),
            ),
            if (_resend)
              TextButton(
                onPressed: _loading ? null : _sendAgain,
                child: const Text('Send a new code'),
              ),
            const SizedBox(height: 12),
            const Text(
              'Never share this code with anyone, including OpFin staff.',
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
}
