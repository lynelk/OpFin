import 'package:flutter/material.dart';
import 'package:opfin/loan_application_screen.dart';

/// Legacy route retained for older navigation references.
/// The authoritative amount and term selection now lives in LoanApplicationScreen.
class LoanAmountScreen extends StatelessWidget {
  const LoanAmountScreen({super.key});

  @override
  Widget build(BuildContext context) => const LoanApplicationScreen();
}
