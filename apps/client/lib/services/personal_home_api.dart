import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class PersonalHomeApi {
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

  static Future<Map<String, dynamic>> load() async {
    final headers = await _headers();
    final responses = await Future.wait([
      http.get(Uri.parse('$apiUrl/financial-compass'), headers: headers),
      http.get(Uri.parse('$apiUrl/credit/profile'), headers: headers),
      http.get(Uri.parse('$apiUrl/protection/policies'), headers: headers),
      http.get(Uri.parse('$apiUrl/financial-spaces'), headers: headers),
    ]);

    final compass = _decode(responses[0], 'Unable to load your financial position.');
    final credit = _decode(responses[1], 'Unable to load your credit position.');
    final protection = _decode(responses[2], 'Unable to load protection status.');
    final spaces = _decode(responses[3], 'Unable to load your financial spaces.');

    return {
      'compass': compass,
      'credit': credit,
      'policies': protection['policies'] ?? const [],
      'spaces': spaces['spaces'] ?? const [],
    };
  }

  static Map<String, dynamic> _decode(http.Response response, String fallback) {
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(decoded['message']?.toString() ?? fallback);
    }

    final data = decoded['data'];
    if (data is Map) return data.cast<String, dynamic>();
    return <String, dynamic>{};
  }
}
