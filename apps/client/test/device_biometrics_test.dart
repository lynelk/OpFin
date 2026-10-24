import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/services/device_biometrics.dart';
import 'package:opfin/services/user_session.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('co.opfin/device_biometrics');
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  var available = true;
  var authenticated = true;
  var prompts = 0;

  setUp(() async {
    available = true;
    authenticated = true;
    prompts = 0;
    FlutterSecureStorage.setMockInitialValues({});
    SharedPreferences.setMockInitialValues({});
    messenger.setMockMethodCallHandler(channel, (call) async {
      if (call.method == 'availability') {
        return {'available': available, 'kind': 'fingerprint'};
      }
      if (call.method == 'authenticate') {
        prompts++;
        return authenticated;
      }
      throw PlatformException(code: 'unsupported');
    });
    await UserSession.saveSession(
      userId: 10,
      accessToken: 'test-session',
      name: 'Test Person',
      role: 'customer',
      phone: 'test-phone',
    );
  });

  tearDown(() {
    messenger.setMockMethodCallHandler(channel, null);
  });

  test('biometrics are opt-in, securely stored and wiped on sign-out', () async {
    final gate = DeviceBiometrics();
    expect(await gate.isEnabledForCurrentUser(), false);
    expect(await gate.enableForCurrentUser(), true);
    expect(prompts, 1);
    expect(await gate.isEnabledForCurrentUser(), true);

    final preferences = await SharedPreferences.getInstance();
    expect(preferences.getKeys().any((key) => key.contains('biometric')), false);

    await UserSession.clear();
    expect(await gate.isEnabledForCurrentUser(), false);
  });

  test('failure, cancellation and unsupported devices never enable biometric unlock', () async {
    final gate = DeviceBiometrics();
    authenticated = false;
    expect(await gate.enableForCurrentUser(), false);
    expect(await gate.isEnabledForCurrentUser(), false);

    available = false;
    authenticated = true;
    expect(await gate.enableForCurrentUser(), false);
    expect(prompts, 1);
  });

  test('biometric permission remains device-local, not a financial authorisation', () async {
    final gate = DeviceBiometrics();
    expect((await gate.availability()).label, 'Touch ID or fingerprint');
    expect(await gate.enableForCurrentUser(), true);
    await gate.disableForCurrentUser();
    expect(await gate.isEnabledForCurrentUser(), false);
  });
}
