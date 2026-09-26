import 'dart:async';
import 'dart:typed_data';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/club_accounting_api.dart';
import 'package:opfin/services/user_session.dart';

class ClubStatementExport {
  ClubStatementExport({http.Client? client, Future<String?> Function()? token, MethodChannel? channel})
      : _client = client ?? http.Client(), _token = token ?? UserSession.getAccessToken,
        _channel = channel ?? const MethodChannel('co.opfin/club_statement_export');
  final http.Client _client;
  final Future<String?> Function() _token;
  final MethodChannel _channel;

  Future<String> open({required int spaceId, required int bookId, required int statementId,
      required String format, required String mode}) async {
    for (final id in [spaceId, bookId, statementId]) { ClubAccountingApi.positiveId(id); }
    if (!['csv','html'].contains(format) || !['save','share','print'].contains(mode)
        || (mode == 'print' && format != 'html')) throw const FormatException('Unsupported statement export.');
    final token = await _token();
    if (token == null || token.isEmpty) throw const FormatException('Sign in before exporting a statement.');
    final request = http.Request('GET', Uri.parse('${apiUrl.replaceFirst(RegExp(r'/$'), '')}'
        '/financial-spaces/$spaceId/accounting/books/$bookId/statements/$statementId/$format'))..followRedirects = false;
    request.headers.addAll({'Authorization':'Bearer $token','Accept':format == 'csv' ? 'text/csv' : 'text/html'});
    final response = await _client.send(request).timeout(const Duration(seconds:30));
    if (response.statusCode != 200) {
      await response.stream.listen(null).cancel();
      throw ClubApiException('The statement is not available for this account. No file was exported.', response.statusCode);
    }
    final expected = format == 'csv' ? 'text/csv' : 'text/html';
    if (!(response.headers['content-type'] ?? '').toLowerCase().startsWith(expected)) {
      await response.stream.listen(null).cancel();
      throw const FormatException('The server did not return the requested statement format.');
    }
    final output = BytesBuilder(copy:false);
    await for (final chunk in response.stream.timeout(const Duration(seconds:15))) {
      if (output.length + chunk.length > 4 * 1024 * 1024) throw const FormatException('Select a shorter statement period for native export.');
      output.add(chunk);
    }
    if (output.length == 0) throw const FormatException('The statement was empty. No file was exported.');
    final result = await _channel.invokeMapMethod<String,dynamic>('export', {
      'filename':'opfin-club-$statementId.$format', 'format':format, 'mode':mode, 'bytes':output.takeBytes(),
    });
    final status = result?['status'];
    if (!['saved','completed','presented','cancelled'].contains(status)) {
      throw const FormatException('The device did not confirm the export result.');
    }
    return status as String;
  }

  void close() => _client.close();
}
