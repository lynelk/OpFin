import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/services/financial_spaces_api.dart';

class SaccoWorkspaceScreen extends StatefulWidget {
  const SaccoWorkspaceScreen({super.key, required this.space});
  final Map<String,dynamic> space;
  @override State<SaccoWorkspaceScreen> createState()=>_SaccoWorkspaceScreenState();
}
class _SaccoWorkspaceScreenState extends State<SaccoWorkspaceScreen> {
  late Future<Map<String,dynamic>> position;
  final money=NumberFormat('#,##0','en_US');
  int get id=>(widget.space['id'] as num).toInt();
  @override void initState(){super.initState();position=FinancialSpacesApi.saccoPosition(id);}
  String ugx(dynamic v)=>'UGX ${money.format((v as num?)?.toInt()??0)}';
  Future<void> reload() async {setState(()=>position=FinancialSpacesApi.saccoPosition(id));await position;}
  Future<void> moneyAction() async {
    final amount=TextEditingController(),phone=TextEditingController();String action='contribution';
    final ok=await showDialog<bool>(context:context,builder:(c)=>StatefulBuilder(builder:(c,setLocal)=>AlertDialog(
      title:const Text('SACCO money action'),content:Column(mainAxisSize:MainAxisSize.min,children:[
        DropdownButtonFormField<String>(initialValue:action,items:const[
          DropdownMenuItem(value:'contribution',child:Text('Contribution / savings')),
          DropdownMenuItem(value:'loan_repayment',child:Text('Loan repayment')),
          DropdownMenuItem(value:'withdrawal',child:Text('Withdrawal request')),
        ],onChanged:(v)=>setLocal(()=>action=v??action)),
        TextField(controller:amount,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'Amount (UGX)')),
        TextField(controller:phone,keyboardType:TextInputType.phone,decoration:const InputDecoration(labelText:'Payment phone')),
      ]),actions:[TextButton(onPressed:()=>Navigator.pop(c,false),child:const Text('Cancel')),FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('Continue'))])));
    final a=int.tryParse(amount.text.replaceAll(',',''))??0;if(ok!=true||a<=0||phone.text.trim().isEmpty)return;
    try{await FinancialSpacesApi.createOperation(id,actionType:action,direction:action=='withdrawal'?'disbursement':'collection',amountMinor:a,phone:phone.text.trim(),idempotencyKey:'app-$id-$action-${DateTime.now().microsecondsSinceEpoch}');if(mounted)ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Request created for review and approval.')));}
    catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.toString())));}
  }
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:Text(widget.space['name']?.toString()??'SACCO')),
    floatingActionButton:FloatingActionButton.extended(onPressed:moneyAction,icon:const Icon(Icons.swap_horiz),label:const Text('Money action')),
    body:FutureBuilder<Map<String,dynamic>>(future:position,builder:(c,s){
      if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());
      if(s.hasError)return Center(child:Padding(padding:const EdgeInsets.all(24),child:Text(s.error.toString())));
      final d=s.data??{},accounts=(d['accounts'] as List? ?? const []),guarantees=(d['guarantees'] as List? ?? const []);
      return RefreshIndicator(onRefresh:reload,child:ListView(padding:const EdgeInsets.all(20),children:[
        const Text('My cooperative position',style:TextStyle(fontSize:24,fontWeight:FontWeight.w800)),
        const SizedBox(height:6),Text('Savings ${ugx(d['total_savings_minor'])} · Held ${ugx(d['total_held_minor'])}'),const SizedBox(height:16),
        ...accounts.map((a)=>Card(child:ListTile(leading:const Icon(Icons.account_balance_wallet_outlined),title:Text(a['name']?.toString()??'Member account'),subtitle:Text('${a['account_number']} · ${a['product_type']}'),trailing:Text(ugx(a['balance_minor']))))),
        Card(child:ListTile(leading:const Icon(Icons.handshake_outlined),title:const Text('Guarantee exposure'),subtitle:Text('${guarantees.length} active/proposed guarantee records'),trailing:Text(ugx(d['guarantee_exposure_minor'])))),
        Card(child:ListTile(leading:const Icon(Icons.insights_outlined),title:const Text('Operations & analytics'),subtitle:const Text('Collections, disbursements, fees, obligations and reconciliation position.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>SpaceOperationsScreen(space:widget.space))))),
      ]));
    }),
  );
}
class SpaceOperationsScreen extends StatefulWidget {
  const SpaceOperationsScreen({super.key,required this.space});final Map<String,dynamic> space;
  @override State<SpaceOperationsScreen> createState()=>_SpaceOperationsScreenState();
}
class _SpaceOperationsScreenState extends State<SpaceOperationsScreen> {
  late Future<Map<String,dynamic>> data;final money=NumberFormat('#,##0','en_US');
  int get id=>(widget.space['id'] as num).toInt();
  @override void initState(){super.initState();data=FinancialSpacesApi.operationsAnalytics(id);}
  String ugx(dynamic v)=>'UGX ${money.format((v as num?)?.toInt()??0)}';
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:const Text('Operations & analytics')),body:FutureBuilder<Map<String,dynamic>>(future:data,builder:(c,s){
    if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());if(s.hasError)return Center(child:Text(s.error.toString()));
    final d=s.data??{},o=(d['operations'] as Map?)??{},ob=(d['obligations'] as Map?)??{},co=(d['commercial'] as Map?)??{};
    return ListView(padding:const EdgeInsets.all(20),children:[
      tile('Collections',ugx(o['collections_minor']),Icons.call_received),tile('Disbursements',ugx(o['disbursements_minor']),Icons.call_made),
      tile('Pending approvals','${o['pending_approval']??0}',Icons.approval_outlined),tile('Outstanding obligations',ugx(ob['outstanding_minor']),Icons.event_note_outlined),
      tile('Platform fees',ugx(o['platform_fees_minor']),Icons.receipt_long_outlined),tile('Recorded OpFin revenue',ugx(co['revenue_minor']),Icons.analytics_outlined),
      const Padding(padding:EdgeInsets.only(top:12),child:Text('Recorded analytics are not a substitute for bank, custodian or provider confirmation.',style:TextStyle(fontSize:12))),
    ]);
  }));
  Widget tile(String t,String v,IconData i)=>Card(child:ListTile(leading:Icon(i),title:Text(t),trailing:Text(v,style:const TextStyle(fontWeight:FontWeight.w700))));
}
