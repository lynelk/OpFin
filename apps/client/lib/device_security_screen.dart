import 'package:flutter/material.dart';
import 'package:opfin/services/device_biometrics.dart';

class DeviceSecurityScreen extends StatefulWidget {
  const DeviceSecurityScreen({super.key});

  @override
  State<DeviceSecurityScreen> createState() => _DeviceSecurityScreenState();
}

class _DeviceSecurityScreenState extends State<DeviceSecurityScreen> {
  final DeviceBiometrics _biometrics = DeviceBiometrics();
  DeviceBiometricAvailability? _available;
  bool _enabled = false;
  bool _busy = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final a = await _biometrics.availability();
    final e = await _biometrics.isEnabledForCurrentUser();
    if (!mounted) return;
    setState(() {
      _available = a;
      _enabled = e;
      _busy = false;
    });
  }

  Future<void> _setEnabled(bool target) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      if (target) {
        final enabled = await _biometrics.enableForCurrentUser();
        if (!mounted) return;
        setState(() => _enabled = enabled);
        if (!enabled) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Biometric confirmation was not completed. Your OpFin PIN remains available.'),
          ));
        }
      } else {
        await _biometrics.disableForCurrentUser();
        if (mounted) setState(() => _enabled = false);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Device security')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Icon(Icons.fingerprint, size: 52),
        const SizedBox(height: 16),
        const Text('Secure your device access',
          style: TextStyle(fontSize: 23, fontWeight: FontWeight.w700)),
        const SizedBox(height: 10),
        const Text('Use the fingerprint or face recognition enrolled on your device to unlock OpFin when opening a saved session. Your biometric data stays with your device.'),
        const SizedBox(height: 20),
        SwitchListTile.adaptive(
          title: const Text('Use device biometrics'),
          subtitle: Text(_available == null
              ? 'Checking this device'
              : !_available!.available
                  ? 'No enrolled or supported device biometrics. Keep using your OpFin PIN.'
                  : '${_available!.label} is available. Opt in to enable it for this account on this device.'),
          value: _enabled,
          onChanged: _busy || (!_enabled && _available?.available != true)
              ? null
              : _setEnabled,
          secondary: const Icon(Icons.shield_outlined),
        ),
        const SizedBox(height: 16),
        const Text('Biometric unlock is optional and does not replace your six-digit OpFin PIN, identity verification, transaction approval or your institution’s authorisation. If you cancel or lose access to your device biometrics, sign in with your PIN again.'),
      ],
    ),
  );
}
