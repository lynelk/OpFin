import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/offline_money_check_screen.dart';

void main() {
  group('MoneyCheckProjection', () {
    test('estimates remaining money without a provider', () {
      expect(
        MoneyCheckProjection.remaining(
          available: 350000,
          bills: 150000,
          essentials: 120000,
        ),
        80000,
      );
    });

    test('shows a deficit instead of masking a shortfall', () {
      expect(
        MoneyCheckProjection.remaining(
          available: 100000,
          bills: 120000,
          essentials: 30000,
        ),
        -50000,
      );
    });

    test('rejects negative amounts', () {
      expect(
        () => MoneyCheckProjection.remaining(
          available: -1,
          bills: 0,
          essentials: 0,
        ),
        throwsArgumentError,
      );
    });
  });

  testWidgets('money check works locally without signup or network calls',
      (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: OfflineMoneyCheckScreen()),
    );
    expect(find.text('Quick money check'), findsOneWidget);
    expect(find.text('Enter all three amounts to see a simple estimate. '
        'Use 0 where an amount is genuinely zero.'), findsOneWidget);

    final fields = find.byType(TextField);
    await tester.enterText(fields.at(0), '100000');
    await tester.enterText(fields.at(1), '120000');
    await tester.enterText(fields.at(2), '30000');
    await tester.pump();

    expect(find.text('UGX -50,000'), findsOneWidget);
    expect(find.textContaining('not saved'), findsOneWidget);
  });
}
