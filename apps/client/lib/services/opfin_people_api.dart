import 'dart:convert';

import 'package:opfin/constants.dart';
import 'package:opfin/services/opfin_http.dart';
import 'package:opfin/services/user_session.dart';

class OpFinPeopleApi {
  static Future<Map<String, dynamic>> _request(
      int spaceId, String path, {Map<String, dynamic>? payload}) async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw StateError('Sign in to access OpFin People.');
    }
    final uri = Uri.parse('$apiUrl/people/spaces/$spaceId/$path');
    final headers = <String, String>{
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    final response = payload == null
        ? await OpFinHttp.get(uri, headers: headers)
        : await OpFinHttp.post(uri, headers: headers, body: jsonEncode(payload));
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw StateError(decoded['message']?.toString() ??
          'This People action could not be completed.');
    }

    return (decoded['data'] as Map? ?? const {}).cast<String, dynamic>();
  }

  static Future<Map<String, dynamic>> load(
      int spaceId, String path) => _request(spaceId, path);

  static Future<Map<String, dynamic>> submit(
      int spaceId, String path, Map<String, dynamic> payload) =>
      _request(spaceId, path, payload: payload);

  static List<Map<String, dynamic>> records(
      Map<String, dynamic> data, String key) {
    final raw = data[key];
    final list = raw is Map ? raw['data'] as List? ?? const [] : raw as List? ?? const [];
    return list.whereType<Map>()
        .map((item) => item.cast<String, dynamic>()).toList();
  }

  static Future<List<Map<String, dynamic>>> businessSpaces() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw StateError('Sign in to view employment connections.');
    }
    final response = await OpFinHttp.get(
      Uri.parse('$apiUrl/people/business-spaces'),
      headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
    );
    if (response.statusCode != 200) {
      throw StateError('Employment connections are unavailable. Please try again.');
    }
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    final data = (decoded['data'] as Map).cast<String, dynamic>();
    return (data['spaces'] as List? ?? const [])
        .map((space) => (space as Map).cast<String, dynamic>())
        .toList();
  }
}
