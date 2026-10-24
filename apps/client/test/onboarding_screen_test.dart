import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/onboarding_screen.dart';

void main(){
  testWidgets('onboarding opens with a financial life and offline-first promise',(tester) async{
    await tester.pumpWidget(const MaterialApp(home:OnboardingScreen()));
    expect(find.text('Know where you stand'),findsOneWidget);
    expect(find.textContaining('Track what you have'),findsOneWidget);
    expect(find.text('Try a money check without internet'),findsOneWidget);
    expect(find.text('Next'),findsOneWidget);
    expect(find.text('Skip'),findsOneWidget);
  });
}
