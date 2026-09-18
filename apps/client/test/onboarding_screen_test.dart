import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/onboarding_screen.dart';

void main(){
  testWidgets('onboarding opens with plain borrower value message',(tester) async{
    await tester.pumpWidget(const MaterialApp(home:OnboardingScreen()));
    expect(find.text('Know what matters'),findsOneWidget);
    expect(find.textContaining('what you can borrow'),findsOneWidget);
    expect(find.text('Next'),findsOneWidget);
    expect(find.text('Skip'),findsOneWidget);
  });
}
