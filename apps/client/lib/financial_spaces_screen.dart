import 'package:flutter/material.dart';
import 'package:opfin/club_accounting/history.dart';
import 'financial_spaces_content.dart' as content;
export 'financial_spaces_content.dart' hide FinancialSpacesScreen;

class FinancialSpacesScreen extends StatelessWidget {
  const FinancialSpacesScreen({super.key});
  @override Widget build(BuildContext context) => Scaffold(
    body:const content.FinancialSpacesScreen(),
    bottomNavigationBar:SafeArea(child:TextButton.icon(
      icon:const Icon(Icons.history),
      label:const Text('My club history, including former memberships'),
      onPressed:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const ClubHistoryScreen())),
    )),
  );
}
