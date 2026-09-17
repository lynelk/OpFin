import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/services/user_session.dart';
import 'package:opfin/store_ready_more_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({'has_seen_onboarding': true});
    FlutterSecureStorage.setMockInitialValues({});
  });

  Future<void> saveExampleSession() => UserSession.saveSession(
        userId: 100,
        accessToken: 'test-only-session-token',
        name: 'Example Customer',
        role: 'customer',
        phone: 'test-only-phone',
        nationalId: 'test-only-national-id',
        dateOfBirth: '1990-01-01',
        ninStatus: 'pending',
        creditScore: 0,
        creditBand: 'unassessed',
        creditRating: 'unassessed',
        defaultingPercentage: 0,
      );

  test('sensitive session fields round-trip through secure storage', () async {
    await saveExampleSession();
    expect(await UserSession.getAccessToken(), 'test-only-session-token');
    expect(await UserSession.getUserId(), 100);
    expect(await UserSession.getPhone(), 'test-only-phone');
    expect(await UserSession.getNationalId(), 'test-only-national-id');
    final preferences = await SharedPreferences.getInstance();
    for (final key in ['access_token', 'user_id', 'phone', 'national_id', 'date_of_birth', 'nin_status']) {
      expect(preferences.containsKey(key), isFalse, reason: '$key must not be written to plain preferences');
    }
  });

  test('logout clears session fields and preserves onboarding preference', () async {
    await saveExampleSession();
    await UserSession.clear();
    expect(await UserSession.getAccessToken(), isNull);
    expect(await UserSession.getUserId(), isNull);
    expect(await UserSession.getNationalId(), isNull);
    final preferences = await SharedPreferences.getInstance();
    expect(preferences.getBool('has_seen_onboarding'), isTrue);
    expect(preferences.containsKey('name'), isFalse);
    expect(preferences.containsKey('credit_score'), isFalse);
  });

  testWidgets('More shows configured production privacy address and deletion', (tester) async {
    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: StoreReadyMoreMobileScreen()),
    ));
    expect(find.text('Privacy policy: https://opfin-production.up.railway.app/privacy-policy'), findsOneWidget);
    expect(find.text('Delete account'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
