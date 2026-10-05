import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:opfin/services/payroll_instruction_client.dart';
import 'package:opfin/services/account_deletion_contract.dart';

class _MemoryStore implements PayrollPendingStore {
  final values = <String, String>{};
  @override
  Future<String?> read(String key) async => values[key];
  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }

  @override
  Future<void> delete(String key) async {
    values.remove(key);
  }
}

void main() {
  test(
      'uncertain writes retain the original retry identity across client instances',
      () async {
    final store = _MemoryStore();
    final keys = <String>[];
    var requests = 0;
    final client = MockClient((request) async {
      keys.add(request.headers['Idempotency-Key']!);
      expect(request.followRedirects, isFalse);
      expect(request.headers['X-Correlation-ID'],
          matches(RegExp(r'^[a-f0-9-]{36}$')));
      requests++;
      if (requests == 1) throw http.ClientException('interrupted');
      return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'case': {'id': 1, 'status': 'reservation_pending'}
            }
          }),
          200);
    });
    final uri = Uri.parse(
        'https://example.test/api/payroll-deduction/cases/1/undertaking');
    final payload = {'authorised': true, 'requested_deduction_minor': 350000};
    await expectLater(
        PayrollInstructionClient(client: client, store: store)
            .send(uri: uri, userId: 1, token: 'test-token', payload: payload),
        throwsA(isA<http.ClientException>()));
    expect(store.values, isNotEmpty);
    await expectLater(
        PayrollInstructionClient(client: client, store: store).send(
            uri: uri,
            userId: 1,
            token: 'test-token',
            payload: {'authorised': true, 'requested_deduction_minor': 360000}),
        throwsException);
    expect(requests, 1);
    await PayrollInstructionClient(client: client, store: store)
        .send(uri: uri, userId: 1, token: 'test-token', payload: payload);
    expect(keys[0], keys[1]);
    expect(store.values, isEmpty);
    client.close();
  });

  test(
      'an invalid successful response does not discard an uncertain instruction',
      () async {
    final store = _MemoryStore();
    final client = MockClient(
        (_) async => http.Response('{"success":true,"data":{}}', 200));
    await expectLater(
        PayrollInstructionClient(client: client, store: store).send(
          uri: Uri.parse(
              'https://example.test/api/payroll-deduction/cases/1/cancel'),
          userId: 1,
          token: 'test-token',
          payload: {},
        ),
        throwsException);
    expect(store.values, isNotEmpty);
    client.close();
  });

  test('only explicit completed deletion can end the customer session', () {
    for (final status in [
      'pending_obligations',
      'blocked_obligations',
      'data_deleted',
      'unknown'
    ]) {
      expect(
          confirmsAccountClosure(200, {
            'success': true,
            'data': {'deletion_status': status}
          }),
          isFalse);
    }
    expect(
        confirmsAccountClosure(200, {
          'success': true,
          'data': {'deletion_status': 'completed'}
        }),
        isTrue);
    expect(
        confirmsAccountClosure(409, {
          'success': true,
          'data': {'deletion_status': 'completed'}
        }),
        isFalse);
    expect(
        confirmsOptionalDataDeletion(200, {
          'success': true,
          'data': {'deletion_status': 'data_deleted'}
        }),
        isTrue);
    expect(
        confirmsOptionalDataDeletion(200, {
          'success': true,
          'data': {'deletion_status': 'completed'}
        }),
        isFalse);
  });
}
