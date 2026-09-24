import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class FinancialWellbeingApi {
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

  static Future<List<Map<String, dynamic>>> accounts() async {
    final response = await http.get(
      Uri.parse('$apiUrl/financial-accounts'),
      headers: await _headers(),
    );
    final data = _decode(response, 'Unable to load your money accounts.');
    return (data['accounts'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Future<Map<String, dynamic>> addAccount({
    required String displayName,
    required String accountType,
    required int balanceMinor,
    String currency = 'UGX',
  }) async {
    final response = await http.post(
      Uri.parse('$apiUrl/financial-accounts'),
      headers: await _headers(),
      body: jsonEncode({
        'display_name': displayName,
        'account_type': accountType,
        'balance_minor': balanceMinor,
        'currency': currency,
        'observed_at': DateTime.now().toIso8601String(),
      }),
    );
    return _decode(response, 'Unable to add the account.');
  }

  static Future<Map<String, dynamic>> updateAccount(
    int accountId, {
    required int balanceMinor,
    String? displayName,
    String? currency,
  }) async {
    final body = <String, dynamic>{
      'balance_minor': balanceMinor,
      'observed_at': DateTime.now().toIso8601String(),
    };
    if (displayName != null) body['display_name'] = displayName;
    if (currency != null) body['currency'] = currency;

    final response = await http.patch(
      Uri.parse('$apiUrl/financial-accounts/$accountId'),
      headers: await _headers(),
      body: jsonEncode(body),
    );
    return _decode(response, 'Unable to update the account balance.');
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
