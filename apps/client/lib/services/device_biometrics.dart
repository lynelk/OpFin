import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:opfin/services/user_session.dart';

/// Local device assurance only. It is never a server login, a KYC decision,
/// customer consent, maker/checker approval or a payment authorisation.
class DeviceBiometricAvailability {
  const DeviceBiometricAvailability({required this.available, required this.kind});

  final bool available;
  final String kind;

  String get label => switch (kind) {
        'face' => 'Face ID or face unlock',
        'fingerprint' => 'Touch ID or fingerprint',
        _ => 'Device biometrics',
      };
}

class DeviceBiometrics {
  DeviceBiometrics({MethodChannel? channel})
      : _channel = channel ?? const MethodChannel('co.opfin/device_biometrics');

  final MethodChannel _channel;

  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(migrateWithBackup: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.unlocked),
  );

  static String _key(int userId) => 'device_biometrics_enabled:$userId';

  Future<DeviceBiometricAvailability> availability() async {
    try {
      final data = await _channel.invokeMapMethod<String, dynamic>('availability');
      return DeviceBiometricAvailability(
        available: data?['available'] == true,
        kind: data?['kind'] is String ? data!['kind'] as String : 'none',
      );
    } on PlatformException {
      return const DeviceBiometricAvailability(available: false, kind: 'none');
    } on MissingPluginException {
      return const DeviceBiometricAvailability(available: false, kind: 'none');
    }
  }

  /// The native prompt requires enrolled biometrics, not merely the device PIN.
  /// Never retain biometric templates or raw authentication responses.
  Future<bool> verify({required String reason}) async {
    try {
      return await _channel.invokeMethod<bool>('authenticate', {'reason': reason}) == true;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }

  Future<bool> isEnabledForCurrentUser() async {
    final id = await UserSession.getUserId();
    final token = await UserSession.getAccessToken();
    if (id == null || token == null || token.isEmpty) return false;
    return await _storage.read(key: _key(id)) == 'true';
  }

  /// Opt-in only after a successful enrolled biometric prompt and an already
  /// authenticated OpFin session. This does not replace the six-digit PIN.
  Future<bool> enableForCurrentUser() async {
    final id = await UserSession.getUserId();
    final token = await UserSession.getAccessToken();
    if (id == null || token == null || token.isEmpty) return false;
    if (!(await availability()).available) return false;
    if (!await verify(reason: 'Enable biometric unlock for OpFin on this device')) {
      return false;
    }
    if (id != await UserSession.getUserId()) return false;
    await _storage.write(key: _key(id), value: 'true');
    return true;
  }

  Future<void> disableForCurrentUser() async {
    final id = await UserSession.getUserId();
    if (id != null) await _storage.delete(key: _key(id));
  }
}
