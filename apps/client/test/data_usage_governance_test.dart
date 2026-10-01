import 'dart:async';

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

  test('sponsorship eligibility uses the exact approved origin', () {
    final api = Uri.parse(apiUrl);

    expect(
      SponsoredDataPolicy.classify(api.replace(scheme: 'http', port: 80)),
      DataSponsorshipClass.nonSponsored,
    );
    expect(
      SponsoredDataPolicy.classify(api.replace(port: 8443)),
      DataSponsorshipClass.nonSponsored,
    );
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
  test('offline queue rejects normalised sensitive operational payloads', () async {
    for (final key in [
      'nin',
      'nationalId',
      'user_pin',
      'otpCode',
      'refreshToken',
    ]) {
      await expectLater(
        OfflineSyncService.queueEvent(
          'profile_note',
          {key: 'sensitive-value'},
        ),
        throwsA(isA<Exception>()),
        reason: 'Sensitive key $key must never enter SharedPreferences.',
      );
    }
  });

  test('metered client disables redirects outside the exact host boundary', () async {
    var followedRedirects = true;
    final client = OpFinMeteredClient(
      feature: 'redirect_test',
      inner: MockClient((request) async {
        followedRedirects = request.followRedirects;
        return http.Response(
          '',
          302,
          headers: const {'location': 'https://example.com/outside-opfin'},
        );
      }),
    );

    final response = await client.get(Uri.parse('$apiUrl/redirect-test'));
    expect(followedRedirects, isFalse);
    expect(response.statusCode, 302);

    await Future<void>.delayed(Duration.zero);
    final summary = await DataUsageLedger.currentMonthSummary();
    expect(summary['request_count'], 1);
    expect(summary['failure_count'], 1);
    client.close();
  });

  test('cancelled response streams are recorded exactly once as failed', () async {
    final client = OpFinMeteredClient(
      feature: 'cancel_test',
      inner: _TwoChunkClient(),
    );

    final response = await client.send(
      http.Request('GET', Uri.parse('$apiUrl/cancel-test')),
    );
    late StreamSubscription<List<int>> subscription;
    final firstChunk = Completer<void>();
    subscription = response.stream.listen((_) {
      if (!firstChunk.isCompleted) firstChunk.complete();
    });

    await firstChunk.future;
    await subscription.cancel();

    final summary = await DataUsageLedger.currentMonthSummary();
    expect(summary['request_count'], 1);
    expect(summary['failure_count'], 1);
    expect((summary['response_bytes'] as num).toInt(), greaterThan(0));
    client.close();
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

class _TwoChunkClient extends http.BaseClient {
  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final controller = StreamController<List<int>>();
    scheduleMicrotask(() => controller.add(<int>[1, 2, 3]));
    Timer(const Duration(milliseconds: 50), () {
      if (!controller.isClosed) {
        controller.add(<int>[4, 5, 6]);
        controller.close();
      }
    });
    return http.StreamedResponse(controller.stream, 200);
  }
}
