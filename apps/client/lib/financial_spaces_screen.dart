import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/services/financial_spaces_api.dart';

class FinancialSpacesScreen extends StatefulWidget {
  const FinancialSpacesScreen({super.key});
  @override State<FinancialSpacesScreen> createState()=>_FinancialSpacesScreenState();
}
class _FinancialSpacesScreenState extends State<FinancialSpacesScreen>{
  late Future<List<Map<String,dynamic>>> _spaces;
  @override void initState(){super.initState();_spaces=FinancialSpacesApi.spaces();}
  Future<void> _reload() async{setState(()=>_spaces=FinancialSpacesApi.spaces());await _spaces;}
  Future<void> _create() async{
    String type='savings_group'; final name=TextEditingController();
    final ok=await showDialog<bool>(context:context,builder:(c)=>StatefulBuilder(builder:(c,setLocal)=>AlertDialog(
      title:const Text('Add a financial space'),content:Column(mainAxisSize:MainAxisSize.min,children:[
        DropdownButtonFormField<String>(initialValue:type,decoration:const InputDecoration(labelText:'What are you setting up?'),items:const[
          DropdownMenuItem(value:'household',child:Text('Household')),DropdownMenuItem(value:'savings_group',child:Text('Savings group')),
          DropdownMenuItem(value:'business',child:Text('Business')),DropdownMenuItem(value:'sacco',child:Text('SACCO')),
          DropdownMenuItem(value:'investment_fund',child:Text('Investment / fund organisation')),DropdownMenuItem(value:'partner',child:Text('Financial partner')),
        ],onChanged:(v)=>setLocal(()=>type=v??type)),TextField(controller:name,decoration:const InputDecoration(labelText:'Name')),
      ]),actions:[TextButton(onPressed:()=>Navigator.pop(c,false),child:const Text('Cancel')),FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('Create'))])));
    if(ok==true&&name.text.trim().isNotEmpty){ await FinancialSpacesApi.createSpace(type,name.text.trim()); await _reload(); }
  }
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:const Text('My spaces')),floatingActionButton:FloatingActionButton.extended(onPressed:_create,icon:const Icon(Icons.add),label:const Text('Add or join')),body:FutureBuilder<List<Map<String,dynamic>>>(future:_spaces,builder:(c,s){
    if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());
    if(s.hasError)return Center(child:Padding(padding:const EdgeInsets.all(24),child:Text('We could not load your spaces. ${s.error}')));
    final spaces=s.data??[];
    return RefreshIndicator(onRefresh:_reload,child:ListView(padding:const EdgeInsets.all(20),children:[
      const Text('One OpFin identity, all the money you manage.',style:TextStyle(fontSize:24,fontWeight:FontWeight.w800)),
      const SizedBox(height:8),const Text('Your personal money stays private. Groups and organisations only show information you are authorised to see.'),const SizedBox(height:18),
      ...spaces.map((x)=>Card(child:ListTile(leading:Icon(_icon(x['type']?.toString())),title:Text(x['name']?.toString()??'Financial space'),subtitle:Text('${_label(x['type']?.toString())} · ${x['role']??'member'}'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(c,MaterialPageRoute(builder:(_)=>FinancialSpaceDetailScreen(space:x)))))),
    ]));
  }));
  IconData _icon(String? t)=>t=='savings_group'?Icons.groups_outlined:t=='business'?Icons.storefront_outlined:t=='sacco'?Icons.account_balance_outlined:t=='household'?Icons.home_outlined:Icons.account_balance_wallet_outlined;
  String _label(String? t)=>{'personal':'My money','savings_group':'Savings group','business':'Business','sacco':'SACCO','household':'Household','investment_fund':'Investment / fund','partner':'Partner'}[t]??'Organisation';
}

class FinancialSpaceDetailScreen extends StatefulWidget{
  final Map<String,dynamic> space; const FinancialSpaceDetailScreen({super.key,required this.space});
  @override State<FinancialSpaceDetailScreen> createState()=>_FinancialSpaceDetailScreenState();
}
class _FinancialSpaceDetailScreenState extends State<FinancialSpaceDetailScreen>{
  late Future<Map<String,dynamic>> _life; final money=NumberFormat('#,##0','en_US');
  int get id=>(widget.space['id'] as num).toInt();
  @override void initState(){super.initState();_life=FinancialSpacesApi.financialLife(id);}
  Future<void> reload()async{setState(()=>_life=FinancialSpacesApi.financialLife(id));await _life;}
  String ugx(dynamic v)=>'UGX ${money.format((v as num?)?.toInt()??0)}';
  Future<void> quick(String kind)async{
    final name=TextEditingController(),amount=TextEditingController();
    final ok=await showDialog<bool>(context:context,builder:(c)=>AlertDialog(title:Text(kind=='asset'?'Add what you own':kind=='owe'?'Add what you owe':'Add money owed to you'),content:Column(mainAxisSize:MainAxisSize.min,children:[TextField(controller:name,decoration:const InputDecoration(labelText:'Name or person')),TextField(controller:amount,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'Amount (UGX)'))]),actions:[TextButton(onPressed:()=>Navigator.pop(c,false),child:const Text('Cancel')),FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('Save'))]));
    final a=int.tryParse(amount.text.replaceAll(',',''))??0; if(ok==true&&a>0){ if(kind=='asset'){ await FinancialSpacesApi.addAsset(id,'other',name.text,a); } else { await FinancialSpacesApi.addObligation(id,kind=='owe'?'personal_debt':'receivable',kind=='owe'?'i_owe':'owed_to_me',name.text,a); } await reload(); }
  }
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:Text(widget.space['name']?.toString()??'My money')),body:FutureBuilder<Map<String,dynamic>>(future:_life,builder:(c,s){if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());if(s.hasError)return Center(child:Text('${s.error}'));final d=s.data??{};return RefreshIndicator(onRefresh:reload,child:ListView(padding:const EdgeInsets.all(20),children:[
    Text(_label(widget.space['type']?.toString()),style:const TextStyle(fontWeight:FontWeight.w700)),const SizedBox(height:8),
    Card(child:Padding(padding:const EdgeInsets.all(18),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Text('Financial position'),Text(ugx(d['net_worth_minor']),style:const TextStyle(fontSize:30,fontWeight:FontWeight.w800)),const SizedBox(height:8),Text('Safe to spend ${ugx(d['safe_to_spend_minor'])}')] ))),
    Row(children:[Expanded(child:_tile('I own',ugx(d['assets_minor']),()=>quick('asset'))),const SizedBox(width:8),Expanded(child:_tile('I owe',ugx(d['debt_minor']),()=>quick('owe')))]),
    _tile('Owed to me',ugx(d['receivables_minor']),()=>quick('receivable')),
    if(widget.space['type']=='savings_group')Card(child:ListTile(leading:const Icon(Icons.group_add_outlined),title:const Text('Members & invitations'),subtitle:const Text('Manage the group from the app.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>GroupMembersScreen(spaceId:id))))),
  ]));}));
  Widget _tile(String t,String v,VoidCallback tap)=>Card(child:ListTile(title:Text(t),subtitle:Text(v),trailing:const Icon(Icons.add_circle_outline),onTap:tap));
  String _label(String? t)=>t=='savings_group'?'Group money':t=='business'?'Business money':t=='personal'?'My money':'Financial space';
}
class GroupMembersScreen extends StatefulWidget{final int spaceId;const GroupMembersScreen({super.key,required this.spaceId});@override State<GroupMembersScreen> createState()=>_GroupMembersScreenState();}
class _GroupMembersScreenState extends State<GroupMembersScreen>{late Future<List<dynamic>> f;@override void initState(){super.initState();f=FinancialSpacesApi.members(widget.spaceId);}Future<void> invite()async{final p=TextEditingController();final ok=await showDialog<bool>(context:context,builder:(c)=>AlertDialog(title:const Text('Invite member'),content:TextField(controller:p,keyboardType:TextInputType.phone,decoration:const InputDecoration(labelText:'Phone number')),actions:[TextButton(onPressed:()=>Navigator.pop(c,false),child:const Text('Cancel')),FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('Invite'))]));if(ok==true&&p.text.isNotEmpty){ await FinancialSpacesApi.invite(widget.spaceId,p.text,'member'); if(mounted){ ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Invitation created.'))); } }}@override Widget build(BuildContext c)=>Scaffold(appBar:AppBar(title:const Text('Group members')),floatingActionButton:FloatingActionButton.extended(onPressed:invite,icon:const Icon(Icons.person_add),label:const Text('Invite')),body:FutureBuilder<List<dynamic>>(future:f,builder:(c,s){if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());return ListView(padding:const EdgeInsets.all(20),children:(s.data??[]).map((m)=>ListTile(leading:const Icon(Icons.person_outline),title:Text('Member ${m['user_id']}'),subtitle:Text('${m['role']} · ${m['status']}'))).toList());}));}
