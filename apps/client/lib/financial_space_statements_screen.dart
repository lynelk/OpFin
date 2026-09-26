import 'package:flutter/material.dart';
import 'package:opfin/club_accounting/workspace.dart';
import 'package:opfin/financial_space_treasury_screen.dart' as treasury;
export 'package:opfin/financial_space_treasury_screen.dart' hide FinancialSpaceStatementsScreen;

/// Preserve the existing treasury experience and expose the new member-accounting workspace.
class FinancialSpaceStatementsScreen extends StatelessWidget {
  const FinancialSpaceStatementsScreen({super.key, required this.space});
  final Map<String,dynamic> space;
  @override Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Club finance')),
    body: ListView(padding: const EdgeInsets.all(20), children: [
      const Text('Cashbook evidence, member accounting and actual money movement are separate records.'),
      const SizedBox(height:16),
      Card(child: ListTile(leading: const Icon(Icons.account_balance_outlined),
        title: const Text('Member capital and investments'),
        subtitle: const Text('Contributions, ownership, investments, distributions, approvals and financial reports.'),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubAccountingScreen(space:space))))),
      Card(child: ListTile(leading: const Icon(Icons.receipt_long_outlined),
        title: const Text('Treasury and external statements'),
        subtitle: const Text('Existing cashbook balances and issued bank-style OpFin statements.'),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => treasury.FinancialSpaceStatementsScreen(space:space))))),
    ]),
  );
}
