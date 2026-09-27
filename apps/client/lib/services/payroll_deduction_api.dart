import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class PayrollDeductionApi {
  static Future<Map<String, String>> _headers({bool stateChange = false}) async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please sign in again.');
    }
    final headers = <String, String>{
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (stateChange) {
      final now = DateTime.now().toUtc().microsecondsSinceEpoch;
      headers['Idempotency-Key'] = 'mobile-$now';
      headers['X-Correlation-ID'] = 'mobile-$now';
    }
    return headers;
  }

  static Future<Map<String, dynamic>> capability() async {
    final response = await http.get(
      Uri.parse('$apiUrl/payroll-deduction/provider-capability'),
      headers: await _headers(),
    );
    final data = _decodeApiResponse(response, 'Unable to load payroll deduction availability.');
    return (data['provider'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  static Future<List<Map<String, dynamic>>> cases() async {
    final response = await http.get(
      Uri.parse('$apiUrl/payroll-deduction/cases'),
      headers: await _headers(),
    );
    final data = _decodeApiResponse(response, 'Unable to load payroll deduction cases.');
    return (data['cases'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Future<List<Map<String, dynamic>>> financingApplications() async {
    final response = await http.get(
      Uri.parse('$apiUrl/financing-applications'),
      headers: await _headers(),
    );
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to load financing applications.');
    }
    return (decoded['data'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Future<Map<String, dynamic>> startCase({
    required int financingApplicationId,
    String? voteCode,
    String? voteName,
    String? employmentReference,
  }) async {
    final response = await http.post(
      Uri.parse('$apiUrl/payroll-deduction/cases'),
      headers: await _headers(stateChange: true),
      body: jsonEncode({
        'financing_application_id': financingApplicationId,
        if (voteCode != null && voteCode.trim().isNotEmpty) 'vote_code': voteCode.trim(),
        if (voteName != null && voteName.trim().isNotEmpty) 'vote_name': voteName.trim(),
        if (employmentReference != null && employmentReference.trim().isNotEmpty)
          'employment_reference': employmentReference.trim(),
      }),
    );
    final data = _decodeApiResponse(response, 'Unable to start payroll deduction processing.');
    return (data['case'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  static Future<int> grantUndertakingConsent() async {
    final response = await http.post(
      Uri.parse('$apiUrl/consents'),
      headers: await _headers(),
      body: jsonEncode({
        'purpose': 'credit_processing',
        'policy_version': 'payroll-undertaking-v1',
        'channel': 'app',
        'metadata': {
          'scope': 'government_payroll_deduction',
          'authority': 'salary_deduction_repayment',
        },
      }),
    );
    final data = _decodeApiResponse(response, 'Unable to record payroll undertaking consent.');
    final consent = (data['consent'] as Map?)?.cast<String, dynamic>();
    final id = consent?['id'];
    if (id is int) return id;
    return int.tryParse('$id') ?? (throw Exception('Consent was recorded without a valid reference.'));
  }

  static Future<Map<String, dynamic>> requestReservation({
    required int caseId,
    required int requestedDeductionMinor,
    required int consentRecordId,
    String? agreementReference,
  }) async {
    final response = await http.post(
      Uri.parse('$apiUrl/payroll-deduction/cases/$caseId/reservation'),
      headers: await _headers(stateChange: true),
      body: jsonEncode({
        'requested_deduction_minor': requestedDeductionMinor,
        'undertaking_consent_record_id': consentRecordId,
        if (agreementReference != null && agreementReference.trim().isNotEmpty)
          'provider_agreement_reference': agreementReference.trim(),
      }),
    );
    final data = _decodeApiResponse(response, 'Unable to request payroll reservation.');
    return (data['case'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  static Future<Map<String, dynamic>> cancel(int caseId) async {
    final response = await http.post(
      Uri.parse('$apiUrl/payroll-deduction/cases/$caseId/cancel'),
      headers: await _headers(stateChange: true),
    );
    final data = _decodeApiResponse(response, 'Unable to cancel payroll deduction processing.');
    return (data['case'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  static Map<String, dynamic> _decodeApiResponse(http.Response response, String fallback) {
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 ||
        response.statusCode >= 300 ||
        decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? fallback);
    }
    return (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }
}
