import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/payroll_instruction_client.dart';
import 'package:opfin/services/user_session.dart';

class PayrollDeductionApi {
  static Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please sign in again.');
    }
    return {'Authorization': 'Bearer $token', 'Accept': 'application/json'};
  }

  static Future<dynamic> _get(String path) async {
    final response = await http
        .get(Uri.parse('$apiUrl$path'), headers: await _headers())
        .timeout(const Duration(seconds: 30));
    final decoded = jsonDecode(response.body);
    if (response.statusCode != 200 ||
        decoded is! Map ||
        decoded['data'] == null ||
        decoded['success'] == false) {
      throw Exception(
          'Unable to load current payroll information. Please refresh.');
    }
    return decoded['data'];
  }

  static Future<Map<String, dynamic>> capability() async {
    final data = await _get('/payroll-deduction/provider-capability');
    return (data['provider'] as Map).cast<String, dynamic>();
  }

  static Future<List<Map<String, dynamic>>> cases() async {
    final data = await _get('/payroll-deduction/cases');
    return (data['cases'] as List)
        .map((row) => (row as Map).cast<String, dynamic>())
        .toList();
  }

  static Future<List<Map<String, dynamic>>> financingApplications() async {
    final data = await _get('/financing-applications');
    return (data as List)
        .map((row) => (row as Map).cast<String, dynamic>())
        .toList();
  }

  static Future<Map<String, dynamic>> _post(
      String path, Map<String, dynamic> data) async {
    final token = await UserSession.getAccessToken();
    final userId = await UserSession.getUserId();
    if (token == null || token.isEmpty || userId == null) {
      throw Exception('Please sign in again.');
    }
    final client = http.Client();
    try {
      return await PayrollInstructionClient(
              client: client, store: const SecurePayrollPendingStore())
          .send(
              uri: Uri.parse('$apiUrl$path'),
              userId: userId,
              token: token,
              payload: data);
    } finally {
      client.close();
    }
  }

  static Future<Map<String, dynamic>> startCase(
          {required int financingApplicationId,
          String? voteCode,
          String? voteName,
          String? employmentReference}) =>
      _post('/payroll-deduction/cases', {
        'financing_application_id': financingApplicationId,
        if (voteCode != null && voteCode.trim().isNotEmpty)
          'vote_code': voteCode.trim(),
        if (voteName != null && voteName.trim().isNotEmpty)
          'vote_name': voteName.trim(),
        if (employmentReference != null &&
            employmentReference.trim().isNotEmpty)
          'employment_reference': employmentReference.trim(),
      });

  static Future<Map<String, dynamic>> authoriseReservation(
          {required int caseId,
          required int requestedDeductionMinor,
          String? agreementReference}) =>
      _post('/payroll-deduction/cases/$caseId/undertaking', {
        'authorised': true,
        'requested_deduction_minor': requestedDeductionMinor,
        if (agreementReference != null && agreementReference.trim().isNotEmpty)
          'provider_agreement_reference': agreementReference.trim(),
      });

  static Future<Map<String, dynamic>> cancel(int caseId) =>
      _post('/payroll-deduction/cases/$caseId/cancel', <String, dynamic>{});
}
