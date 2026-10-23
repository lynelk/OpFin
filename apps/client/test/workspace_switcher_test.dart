import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/workspace_switcher_screen.dart';

void main() {
  testWidgets('switcher lists only supplied authorised spaces and returns selection',
      (tester) async {
    final selectedName = ValueNotifier<String>('No selection');
    Future<List<Map<String, dynamic>>> load() async => [
      <String, dynamic>{
        'id': 7, 'public_id': 'space-savers', 'type': 'savings_group',
        'name': 'Savers', 'role': 'member',
        'workspace': <String, dynamic>{
          'label': 'Savings Groups',
          'can_view_finances': true,
          'can_edit_finances': false,
        },
      },
    ];

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: Builder(
        builder: (context) => Column(children: [
          TextButton(
            onPressed: () async {
              final picked = await Navigator.push<Map<String, dynamic>>(
                context,
                MaterialPageRoute(builder: (_) =>
                    WorkspaceSwitcherScreen(loadSpaces: load)),
              );
              selectedName.value = picked?['name']?.toString() ?? 'No selection';
            },
            child: const Text('Open switcher'),
          ),
          ValueListenableBuilder<String>(
            valueListenable: selectedName,
            builder: (context, value, _) => Text('Selected: $value'),
          ),
        ]),
      )),
    ));

    await tester.tap(find.text('Open switcher'));
    await tester.pumpAndSettle();
    expect(find.text('Savings Groups · member\nView financial records'), findsOneWidget);
    expect(find.text('Savers'), findsOneWidget);
    expect(find.text('Unauthorised firm'), findsNothing);
    await tester.tap(find.text('Savers'));
    await tester.pumpAndSettle();
    expect(find.text('Selected: Savers'), findsOneWidget);
  });
}
