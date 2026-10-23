import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/services/people_attendance_queue.dart';

void main() {
  test('attendance queue is user-specific and never stores session secrets',
      () async {
    final secure = <String, String>{};
    var currentUserId = 17;
    final queue = PeopleAttendanceQueue(
      currentUser: () async => currentUserId,
      reader: (key) async => secure[key],
      writer: (key, value) async { secure[key] = value; },
    );

    await queue.enqueue(
      spaceId: 12,
      engagementId: 42,
      direction: 'in',
      capturedAt: DateTime.utc(2026, 10, 22, 10, 0, 0),
      idempotencyKey: 'offline-attendance-unique-0001',
    );

    final events = await queue.pending(12);
    expect(events, hasLength(1));
    expect(events.single['occurred_at'], '2026-10-22T10:00:00.000Z');
    expect(events.single['source'], 'mobile');
    expect(events.single.containsKey('access_token'), isFalse);
    expect(secure.values.single.contains('256'), isFalse);

    currentUserId = 18;
    expect(await queue.pending(12), isEmpty);
    currentUserId = 17;
    expect(await queue.pending(12), hasLength(1));
  });

  test('replay-safe sync retains unsent events on transport or HTTP failure',
      () async {
    final secure = <String, String>{};
    final queue = PeopleAttendanceQueue(
      currentUser: () async => 7,
      reader: (key) async => secure[key],
      writer: (key, value) async { secure[key] = value; },
    );
    await queue.enqueue(
      spaceId: 12,
      engagementId: 50,
      direction: 'out',
      idempotencyKey: 'offline-event-replayed-once',
    );
    await queue.enqueue(
      spaceId: 13,
      engagementId: 75,
      direction: 'in',
      idempotencyKey: 'different-space-event-001',
    );

    await expectLater(
      queue.flush(12, (event) async {
        expect(event.containsKey('space_id'), isFalse);
        throw StateError('offline');
      }),
      throwsStateError,
    );
    expect(await queue.pending(12), hasLength(1));

    var transmitted = 0;
    expect(await queue.flush(12, (event) async {
      transmitted++;
      expect(event['idempotency_key'], 'offline-event-replayed-once');
    }), 1);
    expect(transmitted, 1);
    expect(await queue.pending(12), isEmpty);
    expect(await queue.pending(13), hasLength(1));
    final saved = jsonDecode(secure.values.single) as List;
    expect(saved, hasLength(1));
  });

  test('same event key cannot be queued twice and anonymous usage fails',
      () async {
    final secure = <String, String>{};
    var active = true;
    final queue = PeopleAttendanceQueue(
      currentUser: () async => active ? 70 : null,
      reader: (key) async => secure[key],
      writer: (key, value) async { secure[key] = value; },
    );
    await queue.enqueue(
      spaceId: 10,
      engagementId: 11,
      direction: 'in',
      idempotencyKey: 'same-event-id',
    );
    await expectLater(
      queue.enqueue(
        spaceId: 10,
        engagementId: 11,
        direction: 'in',
        idempotencyKey: 'same-event-id',
      ), throwsStateError,
    );
    active = false;
    await expectLater(queue.pending(10), throwsStateError);
  });
}
