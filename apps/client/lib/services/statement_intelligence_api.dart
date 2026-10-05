import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/opfin_http.dart';
import 'package:opfin/services/user_session.dart';

/// Provider statement uploads and results (#128). The server reads, checks and
/// labels every statement; the app only shows those results. Nothing here asks
/// for a statement password, mobile-money PIN or OTP.
class StatementIntelligenceApi {
  static const int maxPdfBytes = 10 * 1024 * 1024;
  static const int maxCsvBytes = 24 * 1024 * 1024;
  static const String _feature = 'statements';

  static String _base(int spaceId) =>
      '$apiUrl/financial-spaces/$spaceId/intelligence';

  /// A stable key for one submission, so a retried upload or appeal is not
  /// recorded twice.
  static String newKey(String purpose) {
    final random = Random.secure().nextInt(1 << 32).toRadixString(16);
    return '$purpose-${DateTime.now().microsecondsSinceEpoch}-$random';
  }

  /// Whether statements are switched on for this Space and this person may use
  /// them. Hides the entry point instead of advertising an unavailable feature.
  static Future<bool> available(int spaceId) async {
    try {
      final response = await OpFinHttp.get(
        Uri.parse('${_base(spaceId)}/issuers'),
        headers: await _headers(),
        feature: _feature,
        operation: 'statement_availability',
      );
      return response.statusCode == 200;
    } catch (_) {
      return false;
    }
  }

  static Future<List<Map<String, dynamic>>> issuers(int spaceId) async =>
      _items(await _get('${_base(spaceId)}/issuers', 'statement_issuers'));

  static Future<List<Map<String, dynamic>>> statements(int spaceId) async =>
      _items(await _get('${_base(spaceId)}/statements', 'statement_list'));

  static Future<Map<String, dynamic>> statement(int spaceId, int id) async =>
      (await _get('${_base(spaceId)}/statements/$id', 'statement_detail')
              as Map)
          .cast<String, dynamic>();

  static Future<Map<String, dynamic>> upload({
    required int spaceId,
    required int issuerVersionId,
    required String currency,
    required String periodStart,
    required String periodEnd,
    required String accountReference,
    required String authorityReference,
    required String authorityExpiresAt,
    required String filePath,
    required String fileName,
    required String idempotencyKey,
    int? supersedesStatementId,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${_base(spaceId)}/statements'),
    );
    request.headers.addAll(await _headers(json: false));
    request.headers['Idempotency-Key'] = idempotencyKey;
    request.fields.addAll({
      'issuer_version_id': '$issuerVersionId',
      'currency': currency,
      'period_start': periodStart,
      'period_end': periodEnd,
      'account_reference': accountReference,
      'authority_reference': authorityReference,
      'authority_confirmed': '1',
      'authority_expires_at': authorityExpiresAt,
      'purpose': 'financial_analysis',
      if (supersedesStatementId != null)
        'supersedes_statement_id': '$supersedesStatementId',
    });
    request.files.add(await http.MultipartFile.fromPath(
      'statement_file',
      filePath,
      filename: fileName,
    ));
    final response = await http.Response.fromStream(await OpFinHttp.send(
      request,
      feature: _feature,
      operation: 'statement_upload',
    ));
    return (_decode(response) as Map).cast<String, dynamic>();
  }

  static Future<void> appeal(
    int spaceId,
    int id,
    String reason,
    String idempotencyKey,
  ) async {
    final response = await OpFinHttp.post(
      Uri.parse('${_base(spaceId)}/statements/$id/appeal'),
      headers: {...await _headers(), 'Idempotency-Key': idempotencyKey},
      body: jsonEncode({'reason': reason}),
      feature: _feature,
      operation: 'statement_appeal',
    );
    _decode(response);
  }

  static Future<void> withdraw(int spaceId, int id) async {
    final response = await OpFinHttp.delete(
      Uri.parse('${_base(spaceId)}/statements/$id/permission'),
      headers: await _headers(),
      feature: _feature,
      operation: 'statement_withdraw',
    );
    _decode(response);
  }

  static Future<dynamic> _get(String url, String operation) async {
    final response = await OpFinHttp.get(
      Uri.parse(url),
      headers: await _headers(),
      feature: _feature,
      operation: operation,
    );
    return _decode(response);
  }

  static List<Map<String, dynamic>> _items(dynamic data) =>
      ((data as Map?)?['items'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => item.cast<String, dynamic>())
          .toList();

  static dynamic _decode(http.Response response) {
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(
        decoded['message']?.toString() ?? 'Unable to complete the request.',
      );
    }
    return decoded['data'];
  }

  static Future<Map<String, String>> _headers({bool json = true}) async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception('Secure session is required.');
    }
    return {
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }
}
