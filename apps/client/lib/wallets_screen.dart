import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/credit_profile_api.dart';
import 'package:opfin/services/user_session.dart';

class WalletsScreen extends StatefulWidget{
  const WalletsScreen({super.key});
  @override State<WalletsScreen> createState()=>_WalletsScreenState();
}
class _WalletsScreenState extends State<WalletsScreen>{
  late Future<List<Map<String,dynamic>>> _wallets;
  @override void initState(){super.initState();_wallets=CreditProfileApi.wallets();}
  Future<void> _default(Map<String,dynamic> w) async{
    final token=await UserSession.getAccessToken();
    final r=await http.patch(Uri.parse('$apiUrl/wallets/${w['id']}/default'),headers:{
      'Authorization':'Bearer $token','Accept':'application/json','Content-Type':'application/json'
    },body:jsonEncode({'use_for':'both'}));
    final d=jsonDecode(r.body) as Map<String,dynamic>;
    if(r.statusCode<200||r.statusCode>=300||d['success']!=true){
      if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(d['message']??'Unable to update wallet.')));return;
    }
    setState(()=>_wallets=CreditProfileApi.wallets());
  }
  String _mask(String v)=>v.length<=4?v:'•••• ${v.substring(v.length-4)}';
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:const Text('Wallets')),
    body:FutureBuilder<List<Map<String,dynamic>>>(future:_wallets,builder:(context,s){
      if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());
      if(s.hasError)return Center(child:Text(s.error.toString()));
      final list=s.data??const[];
      return ListView(padding:const EdgeInsets.all(20),children:[
        const Text('Where should money go?',style:TextStyle(fontSize:24,fontWeight:FontWeight.w800)),
        const SizedBox(height:8),
        const Text('Choose one verified wallet for loan payouts and repayments.'),
        const SizedBox(height:18),
        ...list.map((w)=>Card(child:ListTile(
          minVerticalPadding:14,leading:const Icon(Icons.account_balance_wallet_outlined),
          title:Text(_mask(w['msisdn']?.toString()??'')),
          subtitle:Text(w['provider']?.toString()??'Mobile money'),
          trailing:w['is_default_disbursement']==true&&w['is_default_repayment']==true
            ?const Icon(Icons.check_circle)
            :TextButton(onPressed:()=>_default(w),child:const Text('Use'))))),
      ]);
    }));
}
