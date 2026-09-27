import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/services/data_usage_ledger.dart';
import 'package:opfin/services/offline_sync_service.dart';
import 'package:opfin/services/opfin_http.dart';
import 'package:opfin/services/sponsored_data_policy.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
  });

  test('OpFin API traffic is unknown until carrier sponsorship is confirmed', () {
    final classification =
        SponsoredDataPolicy.classify(Uri.parse('$apiUrl/health'));
    expect(classification, DataSponsorshipClass.unknown);
  });

  test('external client traffic is classified as non-sponsored', () {
    final classification = SponsoredDataPolicy.classify(
      Uri.parse('https://example.com/outside-opfin'),
    );
    expect(classification, DataSponsorshipClass.nonSponsored);
  });

  test('metered client records bytes without storing payload content', () async {
    final client = OpFinMeteredClient(
      feature: 'unit_test',
      inner: MockClient((request) async {
        expect(request.headers['X-OpFin-Correlation-Id'], isNotEmpty);
        expect(request.headers['X-OpFin-Feature'], 'unit_test');
        return http.Response('{"ok":true}', 200);
      }),
    );

    final response = await client.post(
      Uri.parse('$apiUrl/test/123'),
      headers: const {'Content-Type': 'application/json'},
      body: '{"secret":"must-not-be-stored"}',
    );
    expect(response.statusCode, 200);

    await Future<void>.delayed(Duration.zero);
    final summary = await DataUsageLedger.currentMonthSummary();
    expect(summary['request_count'], 1);
    expect((summary['request_bytes'] as num).toInt(), greaterThan(0));
    expect((summary['response_bytes'] as num).toInt(), greaterThan(0));

    final encoded = summary.toString();
    expect(encoded, isNot(contains('must-not-be-stored')));
    expect(encoded, isNot(contains('secret')));
    client.close();
  });
  test('offline queue rejects sensitive operational payloads', () async {
    await expectLater(
      OfflineSyncService.queueEvent(
        'profile_note',
        {'nin': 'CM123456789012'},
      ),
      throwsA(isA<Exception>()),
    );
  });

  test('offline queue enforces event and summary budgets', () async {
    await OfflineSyncService.queueEvent(
      'small_event',
      {'note': 'saved offline'},
    );

    final summary = await OfflineSyncService.pendingSummary();
    expect(summary['event_count'], 1);
    expect((summary['queued_bytes'] as num).toInt(), greaterThan(0));

    final oversizedText = List<String>.filled(
      OfflineSyncService.maxEventBytes + 1024,
      'x',
      growable: false,
    ).join();

    await expectLater(
      OfflineSyncService.queueEvent(
        'oversized_event',
        {'note': oversizedText},
      ),
      throwsA(isA<Exception>()),
    );
  });

}
