import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/brand/opfin_theme.dart';
import 'package:opfin/constants.dart';

void main() {
  test('OpFin theme uses shared brand colours and Inter', () {
    final theme = OpFinTheme.light;
    expect(theme.colorScheme.primary, OpFinColors.indigo);
    expect(theme.colorScheme.secondary, OpFinColors.apricot);
    expect(theme.colorScheme.onSecondary, OpFinColors.ink);
    expect(theme.scaffoldBackgroundColor, OpFinColors.ivory);
    expect(theme.textTheme.bodyLarge?.fontFamily, 'Inter');
    expect(theme.textTheme.bodyLarge?.fontSize, 16);
    expect(theme.materialTapTargetSize, MaterialTapTargetSize.padded);
  });

  test('release configuration rejects unencrypted or credential-bearing endpoints', () {
    for (final endpoint in [
      'http://example.test/api',
      'https://user:secret@example.test/api',
      'https://example.test/api?token=example',
      'https://example.test/api#fragment',
      'not-a-url',
    ]) {
      expect(() => validateApiConfiguration(endpoint, release: true), throwsStateError);
    }
    expect(() => validateApiConfiguration('https://example.test/api', release: true), returnsNormally);
    expect(() => validateApiConfiguration('http://localhost:8000/api', release: false), returnsNormally);
    expect(apiUrl, 'https://opfin-production.up.railway.app/api');
  });

  testWidgets('brand symbol and account actions render with enlarged text', (tester) async {
    await tester.pumpWidget(MaterialApp(
      theme: OpFinTheme.light,
      home: MediaQuery(
        data: const MediaQueryData(textScaler: TextScaler.linear(1.8)),
        child: Scaffold(
          body: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(children: [
              const OpFinSymbol(size: 72),
              const Text('Your next step, clearer.'),
              ElevatedButton(onPressed: () {}, child: const Text('Review your offer')),
            ]),
          ),
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.byType(OpFinSymbol), findsOneWidget);
    expect(find.text('Review your offer'), findsOneWidget);
    expect(tester.getSize(find.byType(ElevatedButton)).height, greaterThanOrEqualTo(48));
    expect(tester.takeException(), isNull);
  });
}
