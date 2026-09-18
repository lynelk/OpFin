import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class CreditProfileApi {
  static Future<Map<String, String>> _headers() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please sign in again.');
    }
    return {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  static Future<Map<String, dynamic>> load() async {
    final response = await http.get(
      Uri.parse('$apiUrl/credit/profile'),
      headers: await _headers(),
    );
    return _decode(response, 'Unable to load your credit profile.');
  }

  static Future<Map<String, dynamic>> refresh() async {
    final response = await http.post(
      Uri.parse('$apiUrl/credit/profile/refresh'),
      headers: await _headers(),
    );
    return _decode(response, 'Unable to refresh your credit profile.');
  }

  static Future<List<Map<String, dynamic>>> wallets() async {
    final response =
        await http.get(Uri.parse('$apiUrl/wallets'), headers: await _headers());
    final data = _decode(response, 'Unable to load wallets.');
    return (data['wallets'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Map<String, dynamic> _decode(http.Response response, String fallback) {
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 ||
        response.statusCode >= 300 ||
        decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? fallback);
    }
    return (decoded['data'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
  }
}
