import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/opfin_http.dart';
import 'package:opfin/services/user_session.dart';

class PersonalHomeApi {
  static Map<String, dynamic>? _cachedSnapshot;
  static String? _etag;
  static int? _cachedUserId;

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
    final currentUserId = await UserSession.getUserId();
    if (_cachedUserId != null && _cachedUserId != currentUserId) {
      clearSessionCache();
    }
    _cachedUserId = currentUserId;

    if (_etag != null && _cachedSnapshot != null) {
      headers['If-None-Match'] = _etag!;
    }

    final response = await OpFinHttp.get(
      Uri.parse('$apiUrl/mobile/home'),
      feature: 'home',
      operation: 'mobile_home_refresh',
      headers: headers,
    );

    if (response.statusCode == 304 && _cachedSnapshot != null) {
      return Map<String, dynamic>.from(_cachedSnapshot!);
    }

    if (response.statusCode == 404 || response.statusCode == 405) {
      return _legacyLoad(headers);
    }

    final snapshot = _decode(
      response,
      'Unable to load your financial position.',
    );
    final responseEtag = response.headers['etag'];
    if (responseEtag != null && responseEtag.isNotEmpty) {
      _etag = responseEtag;
      _cachedSnapshot = Map<String, dynamic>.from(snapshot);
    }
    return snapshot;
  }

  static Future<Map<String, dynamic>> _legacyLoad(
    Map<String, String> headers,
  ) async {
    // Compatibility only while API deployments converge. Remove after all
    // supported environments expose /mobile/home.
    final legacyHeaders = Map<String, String>.from(headers)
      ..remove('If-None-Match');

    final compassResponse = await OpFinHttp.get(
      Uri.parse('$apiUrl/financial-compass'),
      feature: 'home',
      operation: 'legacy_financial_compass',
      headers: legacyHeaders,
    );
    final compass = _decode(
      compassResponse,
      'Unable to load your financial position.',
    );

    final optional = await Future.wait([
      _safeGet('/credit/profile', legacyHeaders),
      _safeGet('/protection/policies', legacyHeaders),
      _safeGet('/financial-spaces', legacyHeaders),
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
      'freshness': {
        'legacy_fallback': true,
        'server_authoritative': true,
      },
    };
  }

  static Future<Map<String, dynamic>> _safeGet(
    String path,
    Map<String, String> headers,
  ) async {
    try {
      final response = await OpFinHttp.get(
        Uri.parse('$apiUrl$path'),
        feature: 'home',
        operation: 'legacy_$path',
        headers: headers,
      );
      if (response.statusCode < 200 || response.statusCode >= 300) {
        return <String, dynamic>{};
      }
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      final data = decoded['data'];
      return data is Map
          ? data.cast<String, dynamic>()
          : <String, dynamic>{};
    } catch (_) {
      return <String, dynamic>{};
    }
  }

  static Map<String, dynamic> _decode(
    http.Response response,
    String fallback,
  ) {
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(decoded['message']?.toString() ?? fallback);
    }

    final data = decoded['data'];
    if (data is Map) return data.cast<String, dynamic>();
    return <String, dynamic>{};
  }

  static void clearSessionCache() {
    _etag = null;
    _cachedSnapshot = null;
    _cachedUserId = null;
  }
}
