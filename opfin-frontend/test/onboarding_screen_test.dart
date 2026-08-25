import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/onboarding_screen.dart';

void main() {
  testWidgets('onboarding opens with the financial wellbeing message', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: OnboardingScreen()),
    );

    expect(find.text('Understand your money'), findsOneWidget);
    expect(
      find.text('See what needs attention and what you can do next.'),
      findsOneWidget,
    );
    expect(find.text('Next'), findsOneWidget);
  });
}
