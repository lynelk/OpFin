import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;
import 'package:opfin/services/data_usage_ledger.dart';
import 'package:opfin/services/distribution_channel.dart';
import 'package:opfin/services/sponsored_data_policy.dart';

class OpFinMeteredClient extends http.BaseClient {
  OpFinMeteredClient({
    required this.feature,
    http.Client? inner,
    this.closeInner = true,
  }) : _inner = inner ?? http.Client();

  final String feature;
  final http.Client _inner;
  final bool closeInner;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final started = DateTime.now();
    final operation = request.headers.remove('X-OpFin-Operation') ??
        _canonicalOperation(request.url);
    final sponsorship = SponsoredDataPolicy.classify(request.url);
    final correlationId = request.headers['X-OpFin-Correlation-Id'] ??
        _correlationId();

    // Sponsored eligibility is tied to the exact requested host. Never allow
    // the HTTP stack to silently follow that request onto another host.
    request.followRedirects = false;

    request.headers['X-OpFin-Correlation-Id'] = correlationId;
    request.headers['X-OpFin-Feature'] = _safeHeader(feature);
    request.headers['X-OpFin-Operation'] = _safeHeader(operation);
    request.headers['X-OpFin-Sponsorship-Class'] =
        SponsoredDataPolicy.wireValue(sponsorship);
    request.headers['X-OpFin-Distribution-Channel'] =
        _safeDistributionChannel();

    final requestBytes =
        max(0, request.contentLength) + _headerBytes(request.headers);

    try {
      final response = await _inner.send(request);
      var responseBytes = 0;
      var recorded = false;

      Future<void> recordOnce({required bool completedNormally}) async {
        if (recorded) return;
        recorded = true;
        try {
          await DataUsageLedger.record(
            feature: feature,
            operation: operation,
            requestBytes: requestBytes,
            responseBytes: responseBytes,
            duration: DateTime.now().difference(started),
            success: completedNormally &&
                _successfulStatus(response.statusCode),
            sponsorship: sponsorship,
            statusCode: response.statusCode,
          );
        } catch (_) {
          // Usage telemetry is non-authoritative and must never break the
          // customer's network request or change its financial outcome.
        }
      }

      late StreamSubscription<List<int>> subscription;
      final controller = StreamController<List<int>>(sync: true);

      controller.onListen = () {
        subscription = response.stream.listen(
          (chunk) {
            responseBytes += chunk.length;
            controller.add(chunk);
          },
          onError: (Object error, StackTrace stackTrace) {
            unawaited(recordOnce(completedNormally: false));
            controller.addError(error, stackTrace);
            unawaited(controller.close());
          },
          onDone: () {
            unawaited(recordOnce(completedNormally: true));
            unawaited(controller.close());
          },
          cancelOnError: true,
        );
      };
      controller.onPause = () => subscription.pause();
      controller.onResume = () => subscription.resume();
      controller.onCancel = () async {
        await subscription.cancel();
        await recordOnce(completedNormally: false);
      };

      return http.StreamedResponse(
        controller.stream,
        response.statusCode,
        contentLength: response.contentLength,
        request: response.request,
        headers: response.headers,
        isRedirect: response.isRedirect,
        persistentConnection: response.persistentConnection,
        reasonPhrase: response.reasonPhrase,
      );
    } catch (_) {
      try {
        await DataUsageLedger.record(
          feature: feature,
          operation: operation,
          requestBytes: requestBytes,
          responseBytes: 0,
          duration: DateTime.now().difference(started),
          success: false,
          sponsorship: sponsorship,
        );
      } catch (_) {
        // Measurement failure must not mask the network failure.
      }
      rethrow;
    }
  }

  @override
  void close() {
    if (closeInner) _inner.close();
  }

  static bool _successfulStatus(int statusCode) =>
      (statusCode >= 200 && statusCode < 300) || statusCode == 304;

  static int _headerBytes(Map<String, String> headers) {
    var total = 0;
    for (final entry in headers.entries) {
      total += utf8.encode(entry.key).length +
          utf8.encode(entry.value).length +
          4;
    }
    return total;
  }

  static String _canonicalOperation(Uri uri) {
    final parts = uri.pathSegments.map((segment) {
      if (RegExp(r'^\d+$').hasMatch(segment)) return '{id}';
      if (RegExp(
        r'^[0-9a-fA-F]{8}-[0-9a-fA-F-]{27,}$',
      ).hasMatch(segment)) {
        return '{uuid}';
      }
      return segment.toLowerCase();
    });
    final path = '/${parts.join('/')}';
    return path.length <= 96 ? path : path.substring(0, 96);
  }

  static String _safeHeader(String value) {
    final cleaned = value
        .replaceAll(RegExp(r'[^A-Za-z0-9_.:/{}-]+'), '_')
        .trim();
    if (cleaned.isEmpty) return 'unknown';
    return cleaned.length <= 96 ? cleaned : cleaned.substring(0, 96);
  }

  static String _safeDistributionChannel() {
    try {
      return resolveDistributionChannel();
    } catch (_) {
      return 'unknown';
    }
  }

  static String _correlationId() {
    final random = Random.secure();
    final tail = List<int>.generate(4, (_) => random.nextInt(1 << 16))
        .map((value) => value.toRadixString(16).padLeft(4, '0'))
        .join();
    return 'app-${DateTime.now().microsecondsSinceEpoch.toRadixString(16)}-$tail';
  }
}

class OpFinHttp {
  static final http.Client _inner = http.Client();

  static OpFinMeteredClient _client(String? feature, Uri uri) => OpFinMeteredClient(
        feature: feature ?? _featureFromUri(uri),
        inner: _inner,
        closeInner: false,
      );

  static Future<http.Response> get(
    Uri url, {
    Map<String, String>? headers,
    String? feature,
    String? operation,
  }) =>
      _client(feature, url).get(
        url,
        headers: _headers(headers, operation),
      );

  static Future<http.Response> post(
    Uri url, {
    Map<String, String>? headers,
    Object? body,
    Encoding? encoding,
    String? feature,
    String? operation,
  }) =>
      _client(feature, url).post(
        url,
        headers: _headers(headers, operation),
        body: body,
        encoding: encoding,
      );

  static Future<http.Response> put(
    Uri url, {
    Map<String, String>? headers,
    Object? body,
    Encoding? encoding,
    String? feature,
    String? operation,
  }) =>
      _client(feature, url).put(
        url,
        headers: _headers(headers, operation),
        body: body,
        encoding: encoding,
      );

  static Future<http.Response> patch(
    Uri url, {
    Map<String, String>? headers,
    Object? body,
    Encoding? encoding,
    String? feature,
    String? operation,
  }) =>
      _client(feature, url).patch(
        url,
        headers: _headers(headers, operation),
        body: body,
        encoding: encoding,
      );

  static Future<http.Response> delete(
    Uri url, {
    Map<String, String>? headers,
    Object? body,
    Encoding? encoding,
    String? feature,
    String? operation,
  }) =>
      _client(feature, url).delete(
        url,
        headers: _headers(headers, operation),
        body: body,
        encoding: encoding,
      );

  static Future<http.StreamedResponse> send(
    http.BaseRequest request, {
    String? feature,
    String? operation,
  }) {
    if (operation != null && operation.isNotEmpty) {
      request.headers['X-OpFin-Operation'] = operation;
    }
    return _client(feature, request.url).send(request);
  }

  static String _featureFromUri(Uri uri) {
    final segments = uri.pathSegments
        .where((segment) => segment.isNotEmpty && segment.toLowerCase() != 'api')
        .toList();
    if (segments.isEmpty) return 'core';
    final first = segments.first.toLowerCase().replaceAll(
          RegExp(r'[^a-z0-9_.-]+'),
          '_',
        );
    return first.isEmpty ? 'core' : first;
  }

  static Map<String, String>? _headers(
    Map<String, String>? headers,
    String? operation,
  ) {
    if (operation == null || operation.isEmpty) return headers;
    return <String, String>{
      ...?headers,
      'X-OpFin-Operation': operation,
    };
  }
}
