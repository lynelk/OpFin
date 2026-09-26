import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/services/platform_location_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('co.opfin/location');
  final messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  MethodCall? lastCall;

  setUp(() {
    lastCall = null;
    messenger.setMockMethodCallHandler(channel, (call) async {
      lastCall = call;
      return <String, dynamic>{'actual_precision': 'approximate'};
    });
  });

  tearDown(() {
    messenger.setMockMethodCallHandler(channel, null);
    debugDefaultTargetPlatformOverride = null;
  });

  test('Android downgrades an asset precise request before the native bridge', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    final result = await PlatformLocationService.currentLocation(precision: 'precise');

    expect(PlatformLocationService.supportsPreciseDeviceLocation, isFalse);
    expect(lastCall?.method, 'currentLocation');
    expect(lastCall?.arguments, {'precision': 'approximate'});
    expect(result['actual_precision'], 'approximate');
  });

  test('iOS keeps the explicit task precision', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
    await PlatformLocationService.currentLocation(precision: 'precise');

    expect(PlatformLocationService.supportsPreciseDeviceLocation, isTrue);
    expect(lastCall?.arguments, {'precision': 'precise'});
  });

  test('a denied device request remains an error for manual recovery', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    messenger.setMockMethodCallHandler(channel, (_) async {
      throw PlatformException(
        code: 'location_permission_denied',
        message: 'Location permission was not granted.',
      );
    });

    await expectLater(
      PlatformLocationService.currentLocation(),
      throwsA(isA<Exception>().having(
        (error) => error.toString(),
        'message',
        contains('Location permission was not granted.'),
      )),
    );
  });
}
