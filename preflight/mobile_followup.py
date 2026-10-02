from pathlib import Path

p = Path('apps/client/lib/loan_application_screen.dart')
s = p.read_text()
old = '    if (option == null || !mounted) return;'
assert old in s
p.write_text(s.replace(old, '    if (!mounted) return;'))

p = Path('apps/client/lib/otp_screen.dart')
s = p.read_text()
s = s.replace("import 'dart:convert';", "import 'dart:convert';\nimport 'dart:async';")
s = s.replace("import 'package:flutter/material.dart';", "import 'package:flutter/material.dart';\nimport 'package:flutter/foundation.dart';\nimport 'package:opfin/services/distribution_channel.dart';")
s = s.replace('class _OtpScreenState extends State<OtpScreen> with CodeAutoFill {', 'class _OtpScreenState extends State<OtpScreen> {')
s = s.replace('  final _controller = TextEditingController();', '  final _controller = TextEditingController();\n  StreamSubscription<String>? _smsSubscription;\n  SmsAutoFill? _sms;')
a = s.index('  @override\n  void codeUpdated() {')
b = s.index('  void _tick() {', a)
s = s[:a] + r'''  Future<void> _listenForCodeSafely() async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android ||
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

''' + s[b:]
s = s.replace('    listenForCode();', '    unawaited(_listenForCodeSafely());')
s = s.replace('      listenForCode();', '      unawaited(_listenForCodeSafely());')
s = s.replace('    cancel();', '    unawaited(_disposeAutofill());')
s = s.replace('      setState(() {\n        _countdown = 300;', '      if (!mounted) return;\n      setState(() {\n        _countdown = 300;')
p.write_text(s)
print('Removed the proven redundant option guard and isolated optional native OTP autofill.')
