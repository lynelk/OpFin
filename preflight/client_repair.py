from pathlib import Path

Path('apps/client/lib/services/payroll_instruction_client.dart').write_text(r'''import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;
import 'package:opfin/services/user_session.dart';

abstract class PayrollPendingStore {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

class SecurePayrollPendingStore implements PayrollPendingStore {
  const SecurePayrollPendingStore();
  @override
  Future<String?> read(String key) => UserSession.readPendingPayroll(key);
  @override
  Future<void> write(String key, String value) => UserSession.writePendingPayroll(key, value);
  @override
  Future<void> delete(String key) => UserSession.deletePendingPayroll(key);
}

/// Preserves a financial command's retry identity before attempting its network write.
class PayrollInstructionClient {
  PayrollInstructionClient({required this.client, required this.store});
  final http.Client client;
  final PayrollPendingStore store;
  static final Set<String> _inFlight = <String>{};

  Future<Map<String, dynamic>> send({required Uri uri, required int userId,
      required String token, required Map<String, dynamic> payload}) async {
    final storageKey = 'payroll-pending-v1:$userId:$uri';
    if (!_inFlight.add(storageKey)) {
      throw Exception('This payroll action is already processing.');
    }
    try {
      final body = jsonEncode(_normalise(payload));
      final previous = await store.read(storageKey);
      String idempotencyKey;
      if (previous != null) {
        final pending = jsonDecode(previous) as Map<String, dynamic>;
        if (pending['body'] != body || pending['key'] is! String) {
          throw Exception('A previous payroll action has an uncertain outcome. Retry the original details before changing them.');
        }
        idempotencyKey = pending['key'] as String;
      } else {
        idempotencyKey = _uuid();
        await store.write(storageKey, jsonEncode({'key': idempotencyKey, 'body': body}));
      }
      final request = http.Request('POST', uri)
        ..followRedirects = false
        ..headers.addAll({
          'Authorization': 'Bearer $token', 'Accept': 'application/json',
          'Content-Type': 'application/json', 'Idempotency-Key': idempotencyKey,
          'X-Correlation-ID': _uuid(),
        })
        ..body = body;
      final response = await client.send(request).then(http.Response.fromStream)
          .timeout(const Duration(seconds: 30));
      final decoded = jsonDecode(response.body);
      if (decoded is! Map<String, dynamic>) {
        throw Exception('Payroll response was invalid. Refresh and retry the original action.');
      }
      if (response.statusCode < 200 || response.statusCode >= 300 || decoded['success'] != true) {
        // An explicit validation rejection proves this instruction was not accepted.
        // Authentication, conflicts, timeouts and server failures remain uncertain.
        if (response.statusCode == 422 && decoded['success'] == false) {
          await store.delete(storageKey);
        }
        throw Exception(decoded['message']?.toString() ?? 'Payroll action was not confirmed.');
      }
      final data = decoded['data'];
      final result = data is Map ? data['case'] : null;
      if (result is! Map || result['id'] is! num || result['status'] is! String) {
        throw Exception('Payroll result was not confirmed. Retry the original action.');
      }
      await store.delete(storageKey);
      return result.cast<String, dynamic>();
    } finally {
      _inFlight.remove(storageKey);
    }
  }

  static dynamic _normalise(dynamic value) {
    if (value is Map<String, dynamic>) {
      final keys = value.keys.toList()..sort();
      return {for (final key in keys) key: _normalise(value[key])};
    }
    if (value is List) return value.map(_normalise).toList();
    return value;
  }

  static String _uuid() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final hex = bytes.map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }
}
''')

p = Path('apps/client/lib/services/user_session.dart')
s = p.read_text()
marker = '  static Future<void> clear() async {'
assert marker in s
s = s.replace(marker, r'''  static Future<String?> readPendingPayroll(String key) => _storage.read(key: key);
  static Future<void> writePendingPayroll(String key, String value) => _storage.write(key: key, value: value);
  static Future<void> deletePendingPayroll(String key) => _storage.delete(key: key);

''' + marker)
p.write_text(s)

Path('apps/client/lib/services/payroll_deduction_api.dart').write_text(r'''import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/payroll_instruction_client.dart';
import 'package:opfin/services/user_session.dart';

class PayrollDeductionApi {
  static Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Please sign in again.');
    return {'Authorization': 'Bearer $token', 'Accept': 'application/json'};
  }

  static Future<dynamic> _get(String path) async {
    final response = await http.get(Uri.parse('$apiUrl$path'), headers: await _headers())
        .timeout(const Duration(seconds: 30));
    final decoded = jsonDecode(response.body);
    if (response.statusCode != 200 || decoded is! Map || decoded['data'] == null || decoded['success'] == false) {
      throw Exception('Unable to load current payroll information. Please refresh.');
    }
    return decoded['data'];
  }

  static Future<Map<String, dynamic>> capability() async {
    final data = await _get('/payroll-deduction/provider-capability');
    return (data['provider'] as Map).cast<String, dynamic>();
  }

  static Future<List<Map<String, dynamic>>> cases() async {
    final data = await _get('/payroll-deduction/cases');
    return (data['cases'] as List).map((row) => (row as Map).cast<String, dynamic>()).toList();
  }

  static Future<List<Map<String, dynamic>>> financingApplications() async {
    final data = await _get('/financing-applications');
    return (data as List).map((row) => (row as Map).cast<String, dynamic>()).toList();
  }

  static Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> data) async {
    final token = await UserSession.getAccessToken();
    final userId = await UserSession.getUserId();
    if (token == null || token.isEmpty || userId == null) throw Exception('Please sign in again.');
    final client = http.Client();
    try {
      return await PayrollInstructionClient(client: client, store: const SecurePayrollPendingStore())
          .send(uri: Uri.parse('$apiUrl$path'), userId: userId, token: token, payload: data);
    } finally {
      client.close();
    }
  }

  static Future<Map<String, dynamic>> startCase({required int financingApplicationId,
      String? voteCode, String? voteName, String? employmentReference}) =>
      _post('/payroll-deduction/cases', {
        'financing_application_id': financingApplicationId,
        if (voteCode != null && voteCode.trim().isNotEmpty) 'vote_code': voteCode.trim(),
        if (voteName != null && voteName.trim().isNotEmpty) 'vote_name': voteName.trim(),
        if (employmentReference != null && employmentReference.trim().isNotEmpty)
          'employment_reference': employmentReference.trim(),
      });

  static Future<Map<String, dynamic>> authoriseReservation({required int caseId,
      required int requestedDeductionMinor, String? agreementReference}) =>
      _post('/payroll-deduction/cases/$caseId/undertaking', {
        'authorised': true, 'requested_deduction_minor': requestedDeductionMinor,
        if (agreementReference != null && agreementReference.trim().isNotEmpty)
          'provider_agreement_reference': agreementReference.trim(),
      });

  static Future<Map<String, dynamic>> cancel(int caseId) =>
      _post('/payroll-deduction/cases/$caseId/cancel', <String, dynamic>{});
}
''')

p = Path('apps/client/lib/payroll_deduction_screen.dart')
s = p.read_text()
s = s.replace("final canReserve = status == 'affordable' && affordable > 0;", "final canReserve = {'affordable', 'amendment_required'}.contains(status) && affordable > 0;")
s = s.replace('      final consentId = await PayrollDeductionApi.grantUndertakingConsent();\n', '')
s = s.replace('PayrollDeductionApi.requestReservation(', 'PayrollDeductionApi.authoriseReservation(')
s = s.replace('        consentRecordId: consentId,\n', '')
s = s.replace("        'cancelled' => 'Process cancelled',", "        'cancellation_pending' => 'Awaiting payroll reservation release',\n        'cancelled' => 'Process cancelled',")
s = s.replace("        'vote_rejected' => 'The reservation has been released. Review the reason before another request.',", "        'vote_rejected' => 'Approval was not granted. Review the recorded reason and reservation status before another request.',")
marker = '  String _statusDescription(String status) => switch (status) {'
assert marker in s
s = s.replace(marker, marker + "\n        'cancellation_pending' => 'OpFin is waiting for evidenced provider release. Your servicing access remains available and this reservation is not yet closed.',")
s = s.replace("'This stops the current payroll deduction process. It does not erase financial or audit records that OpFin is required to retain.'", "'This requests cancellation. Any submitted or confirmed reservation stays open until the provider release is verified. Financial and audit records are retained where required.'")
p.write_text(s)

Path('apps/client/lib/services/account_deletion_contract.dart').write_text(r'''bool confirmsAccountClosure(int statusCode, Map<String, dynamic> payload) =>
    statusCode == 200 && payload['success'] == true &&
    payload['data'] is Map && (payload['data'] as Map)['deletion_status'] == 'completed';

bool confirmsOptionalDataDeletion(int statusCode, Map<String, dynamic> payload) =>
    statusCode == 200 && payload['success'] == true &&
    payload['data'] is Map && (payload['data'] as Map)['deletion_status'] == 'data_deleted';
''')
p = Path('apps/client/lib/account_delete_screen.dart')
s = p.read_text()
s = s.replace("import 'package:opfin/services/offline_sync_service.dart';", "import 'package:opfin/services/offline_sync_service.dart';\nimport 'package:opfin/services/account_deletion_contract.dart';")
s = s.replace("if ((data['deletion_status']?.toString() ?? '') != 'completed') {", "if (!confirmsAccountClosure(response.statusCode, decoded)) {")
s = s.replace("if (data['deletion_status'] != 'data_deleted') {", "if (!confirmsOptionalDataDeletion(response.statusCode, decoded)) {")
s = s.replace("            if (amount != null) Text('Amount: $currency ${_money.format(amount)}'),", "            if (amount != null) Text('Amount: $currency ${_money.format(amount)}'),\n            if (item['amount_basis'] == 'contract_total_not_current_balance')\n              const Text('Contract total, not a current outstanding balance.'),")
s = s.replace("      _message(error.toString());\n    } finally {\n      if (mounted) setState(() => _loading = false);", "      if (mounted) setState(() => _readiness = <String, dynamic>{});\n      _message(error.toString());\n    } finally {\n      if (mounted) setState(() => _loading = false);")
p.write_text(s)

# Fix actual analyzer warnings instead of globally hiding them.
p = Path('apps/client/analysis_options.yaml')
s = p.read_text()
s = '\n'.join(line for line in s.splitlines() if not ('unused_import:' in line or 'unnecessary_null_comparison:' in line)) + '\n'
p.write_text(s)
p = Path('apps/client/lib/home_screen.dart')
s = p.read_text().replace("import 'package:opfin/inclusive_finance_screen.dart';\n", '')
p.write_text(s)
p = Path('apps/client/lib/loan_application_screen.dart')
s = p.read_text().replace('amount == null || ', '')
p.write_text(s)

Path('apps/client/test/payroll_instruction_client_test.dart').write_text(r'''import 'dart:convert';
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
  Future<void> write(String key, String value) async { values[key] = value; }
  @override
  Future<void> delete(String key) async { values.remove(key); }
}

void main() {
  test('uncertain writes retain the original retry identity across client instances', () async {
    final store = _MemoryStore();
    final keys = <String>[];
    var requests = 0;
    final client = MockClient((request) async {
      keys.add(request.headers['Idempotency-Key']!);
      expect(request.followRedirects, isFalse);
      expect(request.headers['X-Correlation-ID'], matches(RegExp(r'^[a-f0-9-]{36}$')));
      requests++;
      if (requests == 1) throw http.ClientException('interrupted');
      return http.Response(jsonEncode({'success': true, 'data': {'case': {'id': 1, 'status': 'reservation_pending'}}}), 200);
    });
    final uri = Uri.parse('https://example.test/api/payroll-deduction/cases/1/undertaking');
    final payload = {'authorised': true, 'requested_deduction_minor': 350000};
    await expectLater(PayrollInstructionClient(client: client, store: store).send(uri: uri, userId: 1, token: 'test-token', payload: payload), throwsA(isA<http.ClientException>()));
    expect(store.values, isNotEmpty);
    await expectLater(PayrollInstructionClient(client: client, store: store).send(uri: uri, userId: 1, token: 'test-token', payload: {'authorised': true, 'requested_deduction_minor': 360000}), throwsException);
    expect(requests, 1);
    await PayrollInstructionClient(client: client, store: store).send(uri: uri, userId: 1, token: 'test-token', payload: payload);
    expect(keys[0], keys[1]);
    expect(store.values, isEmpty);
    client.close();
  });

  test('an invalid successful response does not discard an uncertain instruction', () async {
    final store = _MemoryStore();
    final client = MockClient((_) async => http.Response('{"success":true,"data":{}}', 200));
    await expectLater(PayrollInstructionClient(client: client, store: store).send(
      uri: Uri.parse('https://example.test/api/payroll-deduction/cases/1/cancel'),
      userId: 1, token: 'test-token', payload: {},
    ), throwsException);
    expect(store.values, isNotEmpty);
    client.close();
  });

  test('only explicit completed deletion can end the customer session', () {
    for (final status in ['pending_obligations', 'blocked_obligations', 'data_deleted', 'unknown']) {
      expect(confirmsAccountClosure(200, {'success': true, 'data': {'deletion_status': status}}), isFalse);
    }
    expect(confirmsAccountClosure(200, {'success': true, 'data': {'deletion_status': 'completed'}}), isTrue);
    expect(confirmsAccountClosure(409, {'success': true, 'data': {'deletion_status': 'completed'}}), isFalse);
    expect(confirmsOptionalDataDeletion(200, {'success': true, 'data': {'deletion_status': 'data_deleted'}}), isTrue);
    expect(confirmsOptionalDataDeletion(200, {'success': true, 'data': {'deletion_status': 'completed'}}), isFalse);
  });
}
''')
print('Mobile atomic payroll, secure retry and explicit deletion-result contracts prepared.')
