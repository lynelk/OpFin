import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class ClubApiException implements Exception {
  const ClubApiException(this.message, [this.status]);
  final String message;
  final int? status;
  bool get definitiveRejection => const [400, 401, 403, 404, 422].contains(status);
  @override String toString() => message;
}

abstract class ClubAccountingGateway {
  Future<Map<String, dynamic>> call(String action, int spaceId,
      {int? bookId, int? targetId, Map<String, dynamic> input = const {}});
}

class ClubAccountingApi implements ClubAccountingGateway {
  ClubAccountingApi({http.Client? client, Future<String?> Function()? token})
      : _client = client ?? http.Client(), _token = token ?? UserSession.getAccessToken;
  final http.Client _client;
  final Future<String?> Function() _token;

  static int positiveId(dynamic value) {
    if (value is! int || value < 1 || value > 9007199254740991) {
      throw const FormatException('Choose a valid accounting record.');
    }
    return value;
  }

  @override
  Future<Map<String, dynamic>> call(String action, int spaceId,
      {int? bookId, int? targetId, Map<String, dynamic> input = const {}}) async {
    positiveId(spaceId);
    final token = await _token();
    if (token == null || token.isEmpty) throw const FormatException('Sign in again to use club accounting.');
    final root = '/financial-spaces/$spaceId/accounting/books';
    String path;
    String method = 'GET';
    switch (action) {
      case 'schema': path = '/accounting/club-schema'; break;
      case 'profile': path = '/profile'; break;
      case 'books': path = root; break;
      case 'create-book': path = root; method = 'POST'; break;
      case 'catalogue': case 'instructions': case 'report': case 'journals': case 'integrity': case 'statements':
        path = '$root/${positiveId(bookId)}/$action'; break;
      case 'submit': path = '$root/${positiveId(bookId)}/instructions'; method = 'POST'; break;
      case 'issue-statement': path = '$root/${positiveId(bookId)}/statements'; method = 'POST'; break;
      case 'instruction': case 'preview': case 'approve': case 'reject': case 'cancel':
        path = '$root/${positiveId(bookId)}/instructions/${positiveId(targetId)}${action == 'instruction' ? '' : '/$action'}';
        if (action != 'instruction') method = 'POST'; break;
      case 'statement': path = '$root/${positiveId(bookId)}/statements/${positiveId(targetId)}'; break;
      default: throw const FormatException('Unsupported accounting action.');
    }
    final origin = apiUrl.replaceFirst(RegExp(r'/$'), '');
    var uri = Uri.parse('$origin$path');
    if (method == 'GET') {
      const allowed = {'page', 'limit', 'status', 'period_start', 'period_end', 'member_user_id'};
      if (input.keys.any((key) => !allowed.contains(key))) throw const FormatException('Invalid accounting query.');
      uri = uri.replace(queryParameters: input.map((key, value) => MapEntry(key, value.toString())));
    }
    final request = http.Request(method, uri)..followRedirects = false;
    request.headers.addAll({'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer $token'});
    if (method == 'POST') {
      request.body = jsonEncode(input);
      if (request.bodyBytes.length > 200000) throw const FormatException('Split this instruction into smaller reviewed records.');
    }
    try {
      final streamed = await _client.send(request).timeout(const Duration(seconds: 30));
      final bytes = <int>[];
      await for (final chunk in streamed.stream.timeout(const Duration(seconds: 15))) {
        if (bytes.length + chunk.length > 2 * 1024 * 1024) throw const FormatException('Select a smaller accounting report.');
        bytes.addAll(chunk);
      }
      final decoded = jsonDecode(utf8.decode(bytes));
      if (decoded is! Map<String, dynamic>) throw const FormatException('The accounting response was not valid.');
      if (streamed.statusCode < 200 || streamed.statusCode >= 300 || decoded['success'] != true) {
        throw ClubApiException(streamed.statusCode >= 500
            ? 'The service is unavailable. Retain the request key and check the result before retrying.'
            : decoded['message'] is String ? decoded['message'] as String : 'The request was not accepted.', streamed.statusCode);
      }
      final data = decoded['data'];
      if (data is! Map<String, dynamic>) throw const FormatException('The accounting response was incomplete.');
      return data;
    } on TimeoutException {
      throw const FormatException('The result is uncertain. Retry the unchanged instruction with the same request key; do not create a duplicate.');
    } on http.ClientException {
      throw const FormatException('The connection was interrupted. Check the original request before creating another.');
    }
  }

  void close() => _client.close();
}
