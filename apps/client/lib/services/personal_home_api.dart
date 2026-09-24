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

    final compassResponse = await http.get(
      Uri.parse('$apiUrl/financial-compass'),
      headers: headers,
    );
    final compass = _decode(
      compassResponse,
      'Unable to load your financial position.',
    );

    final optional = await Future.wait([
      _safeGet('/credit/profile', headers),
      _safeGet('/protection/policies', headers),
      _safeGet('/financial-spaces', headers),
    ]);

    return {
      'compass': compass,
      'credit': optional[0],
      'policies': optional[1]['policies'] ?? const [],
      'spaces': optional[2]['spaces'] ?? const [],
      'availability': {
        'credit': optional[0].isNotEmpty,
        'protection': optional[1].isNotEmpty,
        'spaces': optional[2].isNotEmpty,
      },
    };
  }

  static Future<Map<String, dynamic>> _safeGet(
    String path,
    Map<String, String> headers,
  ) async {
    try {
      final response = await http.get(
        Uri.parse('$apiUrl$path'),
        headers: headers,
      );
      if (response.statusCode < 200 || response.statusCode >= 300) {
        return <String, dynamic>{};
      }
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      final data = decoded['data'];
      return data is Map ? data.cast<String, dynamic>() : <String, dynamic>{};
    } catch (_) {
      return <String, dynamic>{};
    }
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
