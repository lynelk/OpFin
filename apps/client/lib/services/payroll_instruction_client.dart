import 'dart:convert';
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
  Future<void> write(String key, String value) =>
      UserSession.writePendingPayroll(key, value);
  @override
  Future<void> delete(String key) => UserSession.deletePendingPayroll(key);
}

/// Preserves a financial command's retry identity before attempting its network write.
class PayrollInstructionClient {
  PayrollInstructionClient({required this.client, required this.store});
  final http.Client client;
  final PayrollPendingStore store;
  static final Set<String> _inFlight = <String>{};

  Future<Map<String, dynamic>> send(
      {required Uri uri,
      required int userId,
      required String token,
      required Map<String, dynamic> payload}) async {
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
          throw Exception(
              'A previous payroll action has an uncertain outcome. Retry the original details before changing them.');
        }
        idempotencyKey = pending['key'] as String;
      } else {
        idempotencyKey = _uuid();
        await store.write(
            storageKey, jsonEncode({'key': idempotencyKey, 'body': body}));
      }
      final request = http.Request('POST', uri)
        ..followRedirects = false
        ..headers.addAll({
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Idempotency-Key': idempotencyKey,
          'X-Correlation-ID': _uuid(),
        })
        ..body = body;
      final response = await client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(const Duration(seconds: 30));
      final decoded = jsonDecode(response.body);
      if (decoded is! Map<String, dynamic>) {
        throw Exception(
            'Payroll response was invalid. Refresh and retry the original action.');
      }
      if (response.statusCode < 200 ||
          response.statusCode >= 300 ||
          decoded['success'] != true) {
        // An explicit validation rejection proves this instruction was not accepted.
        // Authentication, conflicts, timeouts and server failures remain uncertain.
        if (response.statusCode == 422 && decoded['success'] == false) {
          await store.delete(storageKey);
        }
        throw Exception(decoded['message']?.toString() ??
            'Payroll action was not confirmed.');
      }
      final data = decoded['data'];
      final result = data is Map ? data['case'] : null;
      if (result is! Map ||
          result['id'] is! num ||
          result['status'] is! String) {
        throw Exception(
            'Payroll result was not confirmed. Retry the original action.');
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
    final hex =
        bytes.map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }
}
