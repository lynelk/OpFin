import 'dart:convert';

import 'package:opfin/services/user_session.dart';

typedef PeopleQueueReader = Future<String?> Function(String key);
typedef PeopleQueueWriter = Future<void> Function(String key, String value);

/// Secure, per-user deferred attendance events. Does not store biometric
/// templates, location, NIN, access tokens or employer financial information.
class PeopleAttendanceQueue {
  PeopleAttendanceQueue({
    Future<int?> Function()? currentUser,
    PeopleQueueReader? reader,
    PeopleQueueWriter? writer,
  })  : _currentUser = currentUser ?? UserSession.getUserId,
        _read = reader ?? _secureRead,
        _write = writer ?? _secureWrite;

  static const int maxPending = 100;

  final Future<int?> Function() _currentUser;
  final PeopleQueueReader _read;
  final PeopleQueueWriter _write;

  static Future<String?> _secureRead(String key) =>
      UserSession.readPendingPayroll(key);
  static Future<void> _secureWrite(String key, String value) =>
      UserSession.writePendingPayroll(key, value);

  Future<String> _key() async {
    final id = await _currentUser();
    if (id == null || id <= 0) {
      throw StateError('Sign in before recording offline attendance.');
    }
    return 'people.attendance.queue.v1.user.$id';
  }

  Future<List<Map<String, dynamic>>> _all() async {
    final value = await _read(await _key());
    if (value == null || value.isEmpty) return [];
    final parsed = jsonDecode(value);
    if (parsed is! List) {
      throw StateError('Encrypted attendance queue needs local review.');
    }
    return parsed
        .map((event) => (event as Map).cast<String, dynamic>())
        .toList();
  }

  Future<void> _save(List<Map<String, dynamic>> items) async {
    await _write(await _key(), jsonEncode(items));
  }

  Future<List<Map<String, dynamic>>> pending(int spaceId) async {
    final items = await _all();
    return items.where((event) => event['space_id'] == spaceId)
        .map((event) => Map<String, dynamic>.from(event)).toList();
  }

  Future<Map<String, dynamic>> enqueue({
    required int spaceId,
    required int engagementId,
    required String direction,
    DateTime? capturedAt,
    String? idempotencyKey,
  }) async {
    if (spaceId <= 0 || engagementId <= 0 || !['in', 'out'].contains(direction)) {
      throw ArgumentError('Invalid organisation, employment or clock direction.');
    }
    final timestamp = (capturedAt ?? DateTime.now()).toUtc();
    final events = await _all();
    if (events.length >= maxPending) {
      throw StateError('Attendance queue is full; sync or resolve pending events.');
    }
    final key = idempotencyKey ??
        'people-offline-$spaceId-$engagementId-${timestamp.microsecondsSinceEpoch}';
    if (events.any((event) => event['idempotency_key'] == key)) {
      throw StateError('Offline attendance event already exists.');
    }
    final event = <String, dynamic>{
      'space_id': spaceId,
      'engagement_id': engagementId,
      'direction': direction,
      'occurred_at': timestamp.toIso8601String(),
      'source': 'mobile',
      'idempotency_key': key,
    };
    events.add(event);
    await _save(events);
    return event;
  }

  /// Send one entry at a time. Remove it only after the authenticated API
  /// confirms a successful idempotent write. Any error preserves the queue.
  Future<int> flush(
      int spaceId,
      Future<void> Function(Map<String, dynamic> payload) sender) async {
    final all = await _all();
    var sent = 0;
    for (final item in List<Map<String, dynamic>>.from(all)) {
      if (item['space_id'] != spaceId) continue;
      final payload = Map<String, dynamic>.from(item)..remove('space_id');
      await sender(payload);
      all.removeWhere((row) => row['idempotency_key'] == item['idempotency_key']);
      await _save(all);
      sent++;
    }
    return sent;
  }
}
