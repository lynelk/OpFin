import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class EssentialsApi {
  static Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Please sign in again.');
    return {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  static Future<Map<String, dynamic>> _request(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
  }) async {
    final uri = Uri.parse('$apiUrl$path');
    final headers = await _headers();
    late http.Response response;
    switch (method) {
      case 'POST':
        response = await http.post(uri, headers: headers, body: jsonEncode(body ?? const {}));
        break;
      case 'DELETE':
        response = await http.delete(uri, headers: headers);
        break;
      default:
        response = await http.get(uri, headers: headers);
    }
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 ||
        response.statusCode >= 300 ||
        decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to complete the Essentials request.');
    }
    return (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  static Future<Map<String, dynamic>> summary() => _request('/essentials');

  static Future<Map<String, dynamic>> catalogue() => _request('/essentials/catalogue');

  static Future<Map<String, dynamic>> refreshEligibility({int? financialSpaceId}) =>
      _request('/essentials/eligibility', method: 'POST', body: {
        if (financialSpaceId != null) 'financial_space_id': financialSpaceId,
        'channel': 'android',
      });

  static Future<Map<String, dynamic>> addAccount({
    required int billerId,
    required String accountReference,
    String? nickname,
    int? financialSpaceId,
    Map<String, dynamic>? metadata,
  }) => _request('/essentials/accounts', method: 'POST', body: {
        'biller_id': billerId,
        'account_reference': accountReference,
        if (nickname != null && nickname.trim().isNotEmpty) 'nickname': nickname.trim(),
        if (financialSpaceId != null) 'financial_space_id': financialSpaceId,
        if (metadata != null) 'metadata': metadata,
      });

  static Future<Map<String, dynamic>> verifyAccount(int accountId) =>
      _request('/essentials/accounts/$accountId/verify', method: 'POST');

  static Future<Map<String, dynamic>> createQuote({
    required int accountId,
    required int amountMinor,
  }) => _request('/essentials/quotes', method: 'POST', body: {
        'essentials_account_id': accountId,
        'amount_minor': amountMinor,
        'channel': 'android',
      });

  static Future<Map<String, dynamic>> acceptQuote({
    required int quoteId,
    required String disclosureHash,
  }) => _request('/essentials/quotes/$quoteId/accept', method: 'POST', body: {
        'disclosure_hash': disclosureHash,
        'accept_disclosures': true,
      });

  static Future<Map<String, dynamic>> repay({
    required int advanceId,
    required int amountMinor,
  }) => _request('/essentials/advances/$advanceId/repay', method: 'POST', body: {
        'amount_minor': amountMinor,
        'idempotency_key': 'android-$advanceId-${DateTime.now().microsecondsSinceEpoch}',
      });

  static Future<Map<String, dynamic>> authorisePartnerPlatform({
    required int partnerAccountId,
    int? financialSpaceId,
    List<String> scopes = const ['eligibility', 'account_write', 'quote_create', 'status_read'],
  }) => _request('/essentials/partner-authorisations', method: 'POST', body: {
        'partner_account_id': partnerAccountId,
        if (financialSpaceId != null) 'financial_space_id': financialSpaceId,
        'scopes': scopes,
        'valid_days': 30,
      });

  static Future<List<Map<String, dynamic>>> partnerAuthorisations() async {
    final data = await _request('/essentials/partner-authorisations');
    return (data['authorisations'] as List? ?? const [])
        .whereType<Map>()
        .map((e) => e.cast<String, dynamic>())
        .toList();
  }

  static Future<void> revokePartnerAuthorisation(int id) async {
    await _request('/essentials/partner-authorisations/$id', method: 'DELETE');
  }
}
