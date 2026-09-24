import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class LocationContextApi {
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

  static Future<Map<String, dynamic>> status() async {
    final response = await http.get(
      Uri.parse('$apiUrl/location/status'),
      headers: await _headers(),
    );
    return _decode(response, 'Unable to load location capability.');
  }

  static Future<List<Map<String, dynamic>>> list({
    required String subjectType,
    required int subjectId,
  }) async {
    final uri = Uri.parse('$apiUrl/location-contexts').replace(queryParameters: {
      'subject_type': subjectType,
      'subject_id': subjectId.toString(),
    });
    final response = await http.get(uri, headers: await _headers());
    final data = _decode(response, 'Unable to load location.');
    return (data['locations'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Future<Map<String, dynamic>> save({
    required String subjectType,
    required int subjectId,
    required String purpose,
    required String source,
    required String precisionLevel,
    required String consentPurpose,
    double? latitude,
    double? longitude,
    int? accuracyMetres,
    String? placeName,
    String? formattedAddress,
    String? placeId,
    String? countryCode,
    String? adminArea1,
    String? adminArea2,
    String? locality,
    bool resolveWithGoogle = false,
  }) async {
    final body = <String, dynamic>{
      'subject_type': subjectType,
      'subject_id': subjectId,
      'purpose': purpose,
      'source': source,
      'precision_level': precisionLevel,
      'consent_purpose': consentPurpose,
      'resolve_with_google': resolveWithGoogle,
    };
    if (latitude != null) body['latitude'] = latitude;
    if (longitude != null) body['longitude'] = longitude;
    if (accuracyMetres != null) body['accuracy_metres'] = accuracyMetres;
    if (placeName != null) body['place_name'] = placeName;
    if (formattedAddress != null) body['formatted_address'] = formattedAddress;
    if (placeId != null) body['google_place_id'] = placeId;
    if (countryCode != null) body['country_code'] = countryCode;
    if (adminArea1 != null) body['admin_area_1'] = adminArea1;
    if (adminArea2 != null) body['admin_area_2'] = adminArea2;
    if (locality != null) body['locality'] = locality;

    final response = await http.post(
      Uri.parse('$apiUrl/location-contexts'),
      headers: await _headers(),
      body: jsonEncode(body),
    );
    final data = _decode(response, 'Unable to save location.');
    return (data['location'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
  }

  static Future<List<Map<String, dynamic>>> autocomplete(
    String query, {
    String? countryCode,
    String? sessionToken,
  }) async {
    final body = <String, dynamic>{'query': query};
    if (countryCode != null) body['country_code'] = countryCode;
    if (sessionToken != null) body['session_token'] = sessionToken;

    final response = await http.post(
      Uri.parse('$apiUrl/location/places/autocomplete'),
      headers: await _headers(),
      body: jsonEncode(body),
    );
    final data = _decode(response, 'Unable to search places.');
    return (data['suggestions'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Future<Map<String, dynamic>> placeDetails(String placeId) async {
    final response = await http.post(
      Uri.parse('$apiUrl/location/places/details'),
      headers: await _headers(),
      body: jsonEncode({'place_id': placeId}),
    );
    final data = _decode(response, 'Unable to load this place.');
    return (data['place'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
  }

  static Future<Map<String, dynamic>> reverseGeocode(
    double latitude,
    double longitude,
  ) async {
    final response = await http.post(
      Uri.parse('$apiUrl/location/reverse-geocode'),
      headers: await _headers(),
      body: jsonEncode({
        'latitude': latitude,
        'longitude': longitude,
      }),
    );
    final data = _decode(response, 'Unable to resolve this location.');
    return (data['place'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
  }

  static Future<List<Map<String, dynamic>>> nearbyServices(
    double latitude,
    double longitude, {
    double radiusKm = 25,
  }) async {
    final uri = Uri.parse('$apiUrl/location/nearby-services').replace(
      queryParameters: {
        'latitude': latitude.toString(),
        'longitude': longitude.toString(),
        'radius_km': radiusKm.toString(),
      },
    );
    final response = await http.get(uri, headers: await _headers());
    final data = _decode(response, 'Unable to load nearby services.');
    return (data['service_points'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => item.cast<String, dynamic>())
        .toList();
  }

  static Uri staticMapUri(int contextId, {int zoom = 14}) =>
      Uri.parse('$apiUrl/location/static-map/$contextId')
          .replace(queryParameters: {'zoom': zoom.toString()});

  static Future<Map<String, String>> imageHeaders() => _headers();

  static Future<void> delete(int contextId) async {
    final response = await http.delete(
      Uri.parse('$apiUrl/location-contexts/$contextId'),
      headers: await _headers(),
    );
    _decode(response, 'Unable to remove location.');
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
