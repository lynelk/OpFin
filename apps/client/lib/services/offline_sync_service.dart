import 'dart:convert';
import 'dart:math';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';
import 'package:shared_preferences/shared_preferences.dart';

class OfflineSyncService {
  static const _queueKey = 'opfin_offline_event_queue_v1';
  static const _deviceKey = 'opfin_device_reference_v1';

  static Future<String> deviceReference() async {
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString(_deviceKey);
    if (existing != null && existing.isNotEmpty) return existing;
    final value = 'device-${DateTime.now().microsecondsSinceEpoch}-${Random.secure().nextInt(1 << 31)}';
    await prefs.setString(_deviceKey, value);
    return value;
  }

  static Future<void> queueEvent(String type, Map<String, dynamic> payload) async {
    final prefs = await SharedPreferences.getInstance();
    final queue = await pendingEvents();
    queue.add({
      'event_id': 'evt-${DateTime.now().microsecondsSinceEpoch}-${Random.secure().nextInt(1 << 31)}',
      'occurred_at': DateTime.now().toUtc().toIso8601String(),
      'type': type,
      'payload': payload,
    });
    await prefs.setString(_queueKey, jsonEncode(queue));
  }

  static Future<List<Map<String, dynamic>>> pendingEvents() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_queueKey);
    if (raw == null || raw.isEmpty) return <Map<String, dynamic>>[];
    try {
      final decoded = jsonDecode(raw) as List<dynamic>;
      return decoded.whereType<Map>().map((entry) => entry.cast<String, dynamic>()).toList();
    } catch (_) {
      return <Map<String, dynamic>>[];
    }
  }

  static Future<Map<String, dynamic>?> sync() async {
    final events = await pendingEvents();
    if (events.isEmpty) return null;
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Secure session is required before offline data can sync.');
    final prefs = await SharedPreferences.getInstance();
    final device = await deviceReference();
    final batchReference = _stableBatchReference(device, events);
    final response = await http.post(
      Uri.parse('$apiUrl/long-range/offline-sync'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
      body: jsonEncode({'batch_reference': batchReference, 'device_reference': device, 'events': events}),
    );
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(decoded['message'] ?? 'Offline synchronization failed.');
    }
    final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    final batch = (data['batch'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    if (batch['status'] == 'processed') {
      await prefs.remove(_queueKey);
    }
    return batch;
  }

  static String _stableBatchReference(String device, List<Map<String, dynamic>> events) {
    final source = '$device|${events.map((e) => e['event_id']).join('|')}';
    final bytes = utf8.encode(source);
    int a = 0x811c9dc5;
    int b = 0x01000193;
    for (final byte in bytes) {
      a = ((a ^ byte) * 0x01000193) & 0xffffffff;
      b = ((b + byte) * 0x45d9f3b) & 0xffffffff;
    }
    final h1 = a.toRadixString(16).padLeft(8, '0');
    final h2 = b.toRadixString(16).padLeft(8, '0');
    final tail = (a ^ b).toRadixString(16).padLeft(8, '0') + a.toRadixString(16).padLeft(8, '0');
    return '$h1-${h2.substring(0, 4)}-${h2.substring(4, 8)}-${tail.substring(0, 4)}-${tail.substring(4, 16)}';
  }
}
