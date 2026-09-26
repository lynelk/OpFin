import 'dart:convert';
import 'dart:math';

import 'package:opfin/constants.dart';
import 'package:opfin/services/opfin_http.dart';
import 'package:opfin/services/user_session.dart';
import 'package:shared_preferences/shared_preferences.dart';

class OfflineSyncService {
  static const _queueKey = 'opfin_offline_event_queue_v2';
  static const _legacyQueueKey = 'opfin_offline_event_queue_v1';
  static const _deviceKey = 'opfin_device_reference_v1';

  static const int maxEventBytes = 64 * 1024;
  static const int maxBatchBytes = 256 * 1024;
  static const int maxQueuedBytes = 2 * 1024 * 1024;
  static const int maxEventsPerBatch = 50;

  static const _forbiddenKeys = <String>{
    'pin',
    'password',
    'otp',
    'access_token',
    'refresh_token',
    'token',
    'national_id',
    'nin',
    'selfie',
    'image',
    'photo',
    'document_bytes',
    'raw_document',
    'provider_payload',
  };

  static Future<String> deviceReference() async {
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString(_deviceKey);
    if (existing != null && existing.isNotEmpty) return existing;
    final value =
        'device-${DateTime.now().microsecondsSinceEpoch}-${Random.secure().nextInt(1 << 31)}';
    await prefs.setString(_deviceKey, value);
    return value;
  }

  static Future<void> queueEvent(
    String type,
    Map<String, dynamic> payload,
  ) async {
    _assertSafePayload(payload);

    final event = <String, dynamic>{
      'event_id':
          'evt-${DateTime.now().microsecondsSinceEpoch}-${Random.secure().nextInt(1 << 31)}',
      'occurred_at': DateTime.now().toUtc().toIso8601String(),
      'type': type,
      'payload': payload,
    };
    final eventBytes = utf8.encode(jsonEncode(event)).length;
    if (eventBytes > maxEventBytes) {
      throw Exception(
        'This offline action is too large. Reconnect before submitting it.',
      );
    }

    final prefs = await SharedPreferences.getInstance();
    final queue = await pendingEvents();
    queue.add(event);
    final encoded = jsonEncode(queue);
    if (utf8.encode(encoded).length > maxQueuedBytes) {
      throw Exception(
        'Offline storage is full. Reconnect and sync saved actions before adding more.',
      );
    }
    await prefs.setString(_queueKey, encoded);
  }

  static Future<List<Map<String, dynamic>>> pendingEvents() async {
    final prefs = await SharedPreferences.getInstance();
    var raw = prefs.getString(_queueKey);
    if ((raw == null || raw.isEmpty) &&
        (prefs.getString(_legacyQueueKey)?.isNotEmpty ?? false)) {
      raw = prefs.getString(_legacyQueueKey);
      if (raw != null && raw.isNotEmpty) {
        await prefs.setString(_queueKey, raw);
        await prefs.remove(_legacyQueueKey);
      }
    }
    if (raw == null || raw.isEmpty) return <Map<String, dynamic>>[];
    try {
      final decoded = jsonDecode(raw) as List<dynamic>;
      return decoded
          .whereType<Map>()
          .map((entry) => entry.cast<String, dynamic>())
          .toList();
    } catch (_) {
      return <Map<String, dynamic>>[];
    }
  }

  static Future<Map<String, dynamic>?> sync() async {
    final events = await pendingEvents();
    if (events.isEmpty) return null;

    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) {
      throw Exception(
        'Secure session is required before offline data can sync.',
      );
    }

    final device = await deviceReference();
    final selected = _boundedBatch(device, events);
    if (selected.isEmpty) {
      throw Exception('The next offline action cannot fit within the sync budget.');
    }

    final batchReference = _stableBatchReference(device, selected);
    final body = jsonEncode({
      'batch_reference': batchReference,
      'device_reference': device,
      'events': selected,
    });
    final response = await OpFinHttp.post(
      Uri.parse('$apiUrl/long-range/offline-sync'),
      feature: 'offline_sync',
      operation: 'offline_sync_batch',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: body,
    );

    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(
        decoded['message'] ?? 'Offline synchronization failed.',
      );
    }

    final data =
        (decoded['data'] as Map?)?.cast<String, dynamic>() ??
            <String, dynamic>{};
    final batch =
        (data['batch'] as Map?)?.cast<String, dynamic>() ??
            <String, dynamic>{};

    if (batch['status'] == 'processed') {
      final sentIds = selected
          .map((event) => event['event_id']?.toString())
          .whereType<String>()
          .toSet();
      final remaining = events
          .where((event) => !sentIds.contains(event['event_id']?.toString()))
          .toList();
      final prefs = await SharedPreferences.getInstance();
      if (remaining.isEmpty) {
        await prefs.remove(_queueKey);
      } else {
        await prefs.setString(_queueKey, jsonEncode(remaining));
      }
      batch['remaining_event_count'] = remaining.length;
    }

    batch['sent_event_count'] = selected.length;
    batch['client_batch_bytes'] = utf8.encode(body).length;
    return batch;
  }

  static List<Map<String, dynamic>> _boundedBatch(
    String device,
    List<Map<String, dynamic>> events,
  ) {
    final selected = <Map<String, dynamic>>[];
    for (final event in events.take(maxEventsPerBatch)) {
      final candidate = [...selected, event];
      final reference = _stableBatchReference(device, candidate);
      final bytes = utf8.encode(jsonEncode({
        'batch_reference': reference,
        'device_reference': device,
        'events': candidate,
      })).length;
      if (bytes > maxBatchBytes) break;
      selected.add(event);
    }
    return selected;
  }

  static void _assertSafePayload(Object? value, [String path = 'payload']) {
    if (value is Map) {
      for (final entry in value.entries) {
        final key = entry.key.toString().trim().toLowerCase();
        if (_forbiddenKeys.contains(key)) {
          throw Exception(
            'Sensitive field "$key" cannot be stored in the offline queue.',
          );
        }
        _assertSafePayload(entry.value, '$path.$key');
      }
      return;
    }
    if (value is Iterable) {
      var index = 0;
      for (final item in value) {
        _assertSafePayload(item, '$path[$index]');
        index++;
      }
    }
  }

  static String _stableBatchReference(
    String device,
    List<Map<String, dynamic>> events,
  ) {
    final source =
        '$device|${events.map((e) => e['event_id']).join('|')}';
    final bytes = utf8.encode(source);
    int a = 0x811c9dc5;
    int b = 0x01000193;
    for (final byte in bytes) {
      a = ((a ^ byte) * 0x01000193) & 0xffffffff;
      b = ((b + byte) * 0x45d9f3b) & 0xffffffff;
    }
    final h1 = a.toRadixString(16).padLeft(8, '0');
    final h2 = b.toRadixString(16).padLeft(8, '0');
    final tail = (a ^ b).toRadixString(16).padLeft(8, '0') +
        a.toRadixString(16).padLeft(8, '0');
    return '$h1-${h2.substring(0, 4)}-${h2.substring(4, 8)}-${tail.substring(0, 4)}-${tail.substring(4, 16)}';
  }
}
