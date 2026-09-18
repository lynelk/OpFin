import 'package:flutter/material.dart';
import 'package:opfin/loan_application_screen.dart';

/// Compatibility entry point retained for older links.
/// Customers now use one simple limit-aware loan application journey.
class ProductsScreen extends StatelessWidget {
  const ProductsScreen({super.key});

  @override
  Widget build(BuildContext context) => const LoanApplicationScreen();
}
