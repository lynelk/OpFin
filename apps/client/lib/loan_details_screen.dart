import 'package:flutter/material.dart';
import 'package:opfin/loan_application_screen.dart';

/// Legacy compatibility route. Short, hard-coded terms are no longer presented.
class LoanDetailsScreen extends StatelessWidget {
  const LoanDetailsScreen({super.key, required this.amount});

  final int amount;

  @override
  Widget build(BuildContext context) =>
      LoanApplicationScreen(initialAmount: amount);
}
