import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class ProtectionApi {
  static Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please sign in again.');
    }
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }

  static Future<List<Map<String, dynamic>>> products({String country = 'UG'}) async {
    final uri = Uri.parse('$apiUrl/protection/products')
        .replace(queryParameters: {'country': country});
    final response = await http.get(uri, headers: await _headers());
    final data = _decode(response, 'Unable to load protection products.');
    return (data['products'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Future<List<Map<String, dynamic>>> policies() async {
    final response = await http.get(
      Uri.parse('$apiUrl/protection/policies'),
      headers: await _headers(),
    );
    final data = _decode(response, 'Unable to load your protection.');
    return (data['policies'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Future<Map<String, dynamic>> enroll(int productId, String disclosureHash) async {
    final response = await http.post(
      Uri.parse('$apiUrl/protection/products/$productId/enroll'),
      headers: await _headers(),
      body: jsonEncode({
        'accept_disclosures': true,
        'disclosure_hash': disclosureHash,
      }),
    );
    return _decode(response, 'Unable to record protection enrolment.');
  }

  static Future<Map<String, dynamic>> payPremium(int policyId, String idempotencyKey) async {
    final response = await http.post(
      Uri.parse('$apiUrl/protection/policies/$policyId/premiums'),
      headers: await _headers(),
      body: jsonEncode({'idempotency_key': idempotencyKey}),
    );
    return _decode(response, 'Unable to start the premium payment.');
  }

  static Future<Map<String, dynamic>> submitClaim(
    int policyId, {
    required String incidentDate,
    required String category,
    required String description,
    int? claimedAmountMinor,
  }) async {
    final body = <String, dynamic>{
      'incident_date': incidentDate,
      'category': category,
      'description': description,
    };
    if (claimedAmountMinor != null) {
      body['claimed_amount_minor'] = claimedAmountMinor;
    }

    final response = await http.post(
      Uri.parse('$apiUrl/protection/policies/$policyId/claims'),
      headers: await _headers(),
      body: jsonEncode(body),
    );
    return _decode(response, 'Unable to submit the claim.');
  }

  static Future<Map<String, dynamic>> disputeClaim(int claimId, String reason) async {
    final response = await http.post(
      Uri.parse('$apiUrl/protection/claims/$claimId/dispute'),
      headers: await _headers(),
      body: jsonEncode({'reason': reason}),
    );
    return _decode(response, 'Unable to open the claim dispute.');
  }

  static Map<String, dynamic> _decode(http.Response response, String fallback) {
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(decoded['message']?.toString() ?? fallback);
    }
    return (decoded['data'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
  }
}
