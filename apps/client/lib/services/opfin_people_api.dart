import 'dart:convert';

import 'package:opfin/constants.dart';
import 'package:opfin/services/opfin_http.dart';
import 'package:opfin/services/user_session.dart';

class OpFinPeopleApi {
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
