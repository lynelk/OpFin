import 'package:flutter/material.dart';
import 'package:opfin/services/club_accounting_api.dart';
import 'workspace_core.dart' as core;
import 'history.dart';
import 'recovery.dart';
export 'workspace_core.dart' hide ClubAccountingScreen;

class ClubAccountingScreen extends StatelessWidget {
  const ClubAccountingScreen({super.key, required this.space, this.gateway});
  final Map<String,dynamic> space;
  final ClubAccountingGateway? gateway;
  @override Widget build(BuildContext context) => Scaffold(
    body:core.ClubAccountingScreen(space:space,gateway:gateway),
    bottomNavigationBar:SafeArea(child:Wrap(alignment:WrapAlignment.center,spacing:8,children:[
      TextButton.icon(icon:const Icon(Icons.restore),label:const Text('Saved requests'),
        onPressed:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>ClubRecoveryScreen(spaceId:space['id'] as int,gateway:gateway)))),
      TextButton.icon(icon:const Icon(Icons.file_download_outlined),label:const Text('Records and exports'),
        onPressed:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>ClubHistoryScreen(spaceId:space['id'] as int,gateway:gateway)))),
    ])),
  );
}
