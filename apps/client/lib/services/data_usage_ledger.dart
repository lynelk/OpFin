import 'dart:convert';

import 'package:opfin/services/sponsored_data_policy.dart';
import 'package:shared_preferences/shared_preferences.dart';

class DataUsageLedger {
  static const _key = 'opfin_data_usage_ledger_v1';
  static Future<void> _tail = Future<void>.value();

  static Future<void> record({
    required String feature,
    required String operation,
    required int requestBytes,
    required int responseBytes,
    required Duration duration,
    required bool success,
    required DataSponsorshipClass sponsorship,
    int? statusCode,
  }) {
    final next = _tail.then((_) => _record(
          feature: _safeDimension(feature),
          operation: _safeDimension(operation),
          requestBytes: requestBytes < 0 ? 0 : requestBytes,
          responseBytes: responseBytes < 0 ? 0 : responseBytes,
          duration: duration,
          success: success,
          sponsorship: sponsorship,
          statusCode: statusCode,
        ));
    _tail = next.catchError((_) {});
    return next;
  }

  static Future<Map<String, dynamic>> currentMonthSummary() async {
    await _tail;
    final prefs = await SharedPreferences.getInstance();
    final root = _decode(prefs.getString(_key));
    final month = _monthKey(DateTime.now().toUtc());
    final summary = root['months']?[month];
    if (summary is Map) return Map<String, dynamic>.from(summary);
    return _newMonth();
  }

  static Future<void> clear() async {
    await _tail;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }

  static Future<void> _record({
    required String feature,
    required String operation,
    required int requestBytes,
    required int responseBytes,
    required Duration duration,
    required bool success,
    required DataSponsorshipClass sponsorship,
    int? statusCode,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    final root = _decode(prefs.getString(_key));
    final months = (root['months'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
    final now = DateTime.now().toUtc();
    final monthKey = _monthKey(now);
    final month = months[monthKey] is Map
        ? Map<String, dynamic>.from(months[monthKey] as Map)
        : _newMonth();

    final totalBytes = requestBytes + responseBytes;
    _increment(month, 'request_count', 1);
    if (!success) _increment(month, 'failure_count', 1);
    _increment(month, 'request_bytes', requestBytes);
    _increment(month, 'response_bytes', responseBytes);
    _increment(month, 'duration_ms', duration.inMilliseconds);

    final sponsorshipKey = switch (sponsorship) {
      DataSponsorshipClass.sponsored => 'sponsored_bytes',
      DataSponsorshipClass.nonSponsored => 'non_sponsored_bytes',
      DataSponsorshipClass.unknown => 'unknown_bytes',
    };
    _increment(month, sponsorshipKey, totalBytes);
    month['last_recorded_at'] = now.toIso8601String();

    final features = (month['features'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
    final featureRow = features[feature] is Map
        ? Map<String, dynamic>.from(features[feature] as Map)
        : <String, dynamic>{
            'request_count': 0,
            'failure_count': 0,
            'request_bytes': 0,
            'response_bytes': 0,
            'sponsored_bytes': 0,
            'non_sponsored_bytes': 0,
            'unknown_bytes': 0,
            'operations': <String, dynamic>{},
          };

    _increment(featureRow, 'request_count', 1);
    if (!success) _increment(featureRow, 'failure_count', 1);
    _increment(featureRow, 'request_bytes', requestBytes);
    _increment(featureRow, 'response_bytes', responseBytes);
    _increment(featureRow, sponsorshipKey, totalBytes);

    final operations =
        (featureRow['operations'] as Map?)?.cast<String, dynamic>() ??
            <String, dynamic>{};
    final operationRow = operations[operation] is Map
        ? Map<String, dynamic>.from(operations[operation] as Map)
        : <String, dynamic>{
            'request_count': 0,
            'failure_count': 0,
            'request_bytes': 0,
            'response_bytes': 0,
            'last_status': null,
          };
    _increment(operationRow, 'request_count', 1);
    if (!success) _increment(operationRow, 'failure_count', 1);
    _increment(operationRow, 'request_bytes', requestBytes);
    _increment(operationRow, 'response_bytes', responseBytes);
    operationRow['last_status'] = statusCode;
    operations[operation] = operationRow;
    featureRow['operations'] = operations;
    features[feature] = featureRow;
    month['features'] = features;

    months[monthKey] = month;
    final sortedKeys = months.keys.toList()..sort();
    while (sortedKeys.length > 6) {
      months.remove(sortedKeys.removeAt(0));
    }

    root['version'] = 1;
    root['months'] = months;
    await prefs.setString(_key, jsonEncode(root));
  }

  static Map<String, dynamic> _decode(String? raw) {
    if (raw == null || raw.isEmpty) return <String, dynamic>{'version': 1};
    try {
      final value = jsonDecode(raw);
      if (value is Map) return Map<String, dynamic>.from(value);
    } catch (_) {}
    return <String, dynamic>{'version': 1};
  }

  static Map<String, dynamic> _newMonth() => <String, dynamic>{
        'request_count': 0,
        'failure_count': 0,
        'request_bytes': 0,
        'response_bytes': 0,
        'duration_ms': 0,
        'sponsored_bytes': 0,
        'non_sponsored_bytes': 0,
        'unknown_bytes': 0,
        'features': <String, dynamic>{},
      };

  static void _increment(Map<String, dynamic> row, String key, int value) {
    row[key] = ((row[key] as num?)?.toInt() ?? 0) + value;
  }

  static String _monthKey(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}';

  static String _safeDimension(String value) {
    final cleaned = value
        .trim()
        .toLowerCase()
        .replaceAll(RegExp(r'[^a-z0-9_.-]+'), '_')
        .replaceAll(RegExp(r'_+'), '_');
    if (cleaned.isEmpty) return 'unknown';
    return cleaned.length <= 64 ? cleaned : cleaned.substring(0, 64);
  }
}
