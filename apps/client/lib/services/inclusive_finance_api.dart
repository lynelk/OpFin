import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class InclusiveFinanceApi {
  static Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception('Secure session is required.');
    }
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
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
        response = await http.post(uri, headers: headers, body: jsonEncode(body ?? {}));
        break;
      case 'PATCH':
        response = await http.patch(uri, headers: headers, body: jsonEncode(body ?? {}));
        break;
      case 'DELETE':
        response = await http.delete(uri, headers: headers, body: jsonEncode(body ?? {}));
        break;
      default:
        response = await http.get(uri, headers: headers);
    }

    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : (jsonDecode(response.body) as Map<String, dynamic>);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to complete request.');
    }

    return (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  static Future<Map<String, dynamic>> profile() =>
      _request('/inclusive-finance/profile');

  static Future<Map<String, dynamic>> updateProfile({
    required bool programmeMeasurementConsent,
    Map<String, dynamic>? measurementAttributes,
  }) =>
      _request(
        '/inclusive-finance/profile',
        method: 'PATCH',
        body: {
          'programme_measurement_consent': programmeMeasurementConsent,
          if (measurementAttributes != null)
            'measurement_attributes': measurementAttributes,
        },
      );

  static Future<Map<String, dynamic>> capability() =>
      _request('/inclusive-finance/capability');

  static Future<Map<String, dynamic>> financialHealth() =>
      _request('/inclusive-finance/impact/financial-health');

  static Future<Map<String, dynamic>> recordFinancialHealth({
    String? incomeStability,
    int? essentialExpenseCoverageDays,
    int? emergencySavingsMinor,
    int? monthlyIncomeMinor,
    bool? repaymentStress,
    bool? insuranceProtection,
    String? savingsDirection,
  }) =>
      _request(
        '/inclusive-finance/impact/financial-health',
        method: 'POST',
        body: {
          'measurement_stage': 'check_in',
          if (incomeStability != null) 'income_stability': incomeStability,
          if (essentialExpenseCoverageDays != null)
            'essential_expense_coverage_days': essentialExpenseCoverageDays,
          if (emergencySavingsMinor != null)
            'emergency_savings_minor': emergencySavingsMinor,
          if (monthlyIncomeMinor != null) 'monthly_income_minor': monthlyIncomeMinor,
          if (repaymentStress != null) 'repayment_stress': repaymentStress,
          if (insuranceProtection != null)
            'insurance_protection': insuranceProtection,
          if (savingsDirection != null) 'savings_direction': savingsDirection,
        },
      );

  static Future<Map<String, dynamic>> fairTreatment() =>
      _request('/inclusive-finance/fair-treatment');

  static Future<Map<String, dynamic>> programmes() =>
      _request('/inclusive-finance/programmes');

  static Future<Map<String, dynamic>> enrol(int programmeId) =>
      _request('/inclusive-finance/programmes/$programmeId/enrol', method: 'POST');

  static Future<Map<String, dynamic>> leaveProgramme(int programmeId) =>
      _request('/inclusive-finance/programmes/$programmeId/enrol', method: 'DELETE');

  static Future<Map<String, dynamic>> supportInstruments() =>
      _request('/inclusive-finance/support-instruments');

  static Future<Map<String, dynamic>> addSupportInstrument({
    required String instrumentType,
    String? providerName,
    String? externalReference,
    int? valueMinor,
  }) =>
      _request(
        '/inclusive-finance/support-instruments',
        method: 'POST',
        body: {
          'instrument_type': instrumentType,
          if (providerName != null && providerName.trim().isNotEmpty)
            'provider_name': providerName.trim(),
          if (externalReference != null && externalReference.trim().isNotEmpty)
            'external_reference': externalReference.trim(),
          if (valueMinor != null) 'value_minor': valueMinor,
          'currency': 'UGX',
        },
      );

  static Future<Map<String, dynamic>> recordCapabilityEvent({
    required String eventType,
    String? interventionCode,
    Map<String, dynamic>? context,
    Map<String, dynamic>? outcome,
  }) =>
      _request(
        '/inclusive-finance/capability/events',
        method: 'POST',
        body: {
          'event_type': eventType,
          if (interventionCode != null) 'intervention_code': interventionCode,
          if (context != null) 'context': context,
          if (outcome != null) 'outcome': outcome,
        },
      );

  static Future<Map<String, dynamic>> dueProgrammeCheckIns({
    String channel = 'app',
    String? locale,
  }) {
    final query = <String, String>{'channel': channel};
    if (locale != null && locale.isNotEmpty) query['locale'] = locale;
    final uri = Uri(path: '/inclusive-finance/programme-check-ins', queryParameters: query);
    return _request(uri.toString());
  }

  static Future<Map<String, dynamic>> submitProgrammeCheckIn({
    required int instrumentId,
    required List<Map<String, dynamic>> answers,
    int? scheduleId,
    String channel = 'app',
    String? locale,
  }) =>
      _request(
        '/inclusive-finance/programme-check-ins/$instrumentId/responses',
        method: 'POST',
        body: {
          if (scheduleId != null) 'schedule_id': scheduleId,
          'channel': channel,
          if (locale != null) 'locale': locale,
          'answers': answers,
        },
      );

  static Future<Map<String, dynamic>> markProgrammeFollowUpOpened(int scheduleId) =>
      _request(
        '/inclusive-finance/programme-follow-ups/$scheduleId/open',
        method: 'POST',
      );

  static Future<Map<String, dynamic>> financialHealthEnrichmentPreview() =>
      _request('/inclusive-finance/impact/financial-health/enrichment');

  static Future<Map<String, dynamic>> recordFinancialHealthEnrichment({
    int? programmeId,
  }) =>
      _request(
        '/inclusive-finance/impact/financial-health/enrichment',
        method: 'POST',
        body: {
          if (programmeId != null) 'programme_id': programmeId,
        },
      );

}
