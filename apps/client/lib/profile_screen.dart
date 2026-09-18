import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/accessibility_screen.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/kyc_setup_screen.dart';
import 'package:opfin/login_screen.dart';
import 'package:opfin/secondary_phone_screen.dart';
import 'package:opfin/services/credit_profile_api.dart';
import 'package:opfin/services/user_session.dart';
import 'package:opfin/wallets_screen.dart';

class ProfileScreen extends StatefulWidget{
  const ProfileScreen({super.key});
  @override State<ProfileScreen> createState()=>_ProfileScreenState();
}
class _ProfileScreenState extends State<ProfileScreen>{
  late Future<Map<String,dynamic>> _data;
  @override void initState(){super.initState();_data=_load();}
  Future<Map<String,dynamic>> _load() async{
    final token=await UserSession.getAccessToken();
    final profile=await http.get(Uri.parse('$apiUrl/profile'),headers:{
      'Authorization':'Bearer $token','Accept':'application/json'});
    final body=jsonDecode(profile.body) as Map<String,dynamic>;
    if(profile.statusCode!=200||body['success']!=true)throw Exception(body['message']??'Unable to load profile.');
    final state=await CreditProfileApi.load();
    return {'account':(body['data'] as Map).cast<String,dynamic>(),'credit':state};
  }
  Future<void> _open(Widget screen) async{
    await Navigator.push(context,MaterialPageRoute(builder:(_)=>screen));
    setState(()=>_data=_load());
  }
  Future<void> _logout() async{
    try{
      final token=await UserSession.getAccessToken();
      await http.post(Uri.parse('$apiUrl/logout'),headers:{
        'Authorization':'Bearer $token','Accept':'application/json'});
    }catch(_){}
    await UserSession.clear();
    if(!mounted)return;
    Navigator.pushAndRemoveUntil(context,MaterialPageRoute(builder:(_)=>const LoginScreen()),(_)=>false);
  }
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:const Text('Profile & security')),
    body:FutureBuilder<Map<String,dynamic>>(future:_data,builder:(context,s){
      if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());
      if(s.hasError)return Center(child:Text(s.error.toString()));
      final account=((s.data!['account'] as Map)['user'] as Map).cast<String,dynamic>();
      final credit=(s.data!['credit'] as Map).cast<String,dynamic>();
      final setup=(credit['setup'] as Map?)?.cast<String,dynamic>()??{};
      return ListView(padding:const EdgeInsets.all(20),children:[
        Semantics(header:true,child:Text(account['name']?.toString()??'',
          style:const TextStyle(fontSize:25,fontWeight:FontWeight.w800))),
        const SizedBox(height:4),
        Text(account['phone']?.toString()??''),
        const SizedBox(height:20),
        Card(child:ListTile(
          leading:Icon(setup['kyc_status']=='verified'?Icons.verified_user:Icons.badge_outlined),
          title:const Text('Identity'),
          subtitle:Text(setup['kyc_status']=='verified'?'Verified':'Not yet verified'),
          trailing:const Icon(Icons.chevron_right),
          onTap:setup['kyc_status']=='verified'?null:()=>_open(const KycSetupScreen()))),
        Card(child:ListTile(
          leading:const Icon(Icons.add_call),
          title:const Text('Second phone'),
          subtitle:Text(setup['secondary_phone_verified']==true?'Verified':'Optional · not added'),
          trailing:setup['secondary_phone_verified']==true?const Icon(Icons.check_circle):const Icon(Icons.chevron_right),
          onTap:setup['secondary_phone_verified']==true?null:()=>_open(const SecondaryPhoneScreen()))),
        Card(child:ListTile(
          leading:const Icon(Icons.account_balance_wallet_outlined),
          title:const Text('Wallets'),subtitle:const Text('Choose payout and repayment wallet.'),
          trailing:const Icon(Icons.chevron_right),onTap:()=>_open(const WalletsScreen()))),
        Card(child:ListTile(
          leading:const Icon(Icons.accessibility_new),
          title:const Text('Accessibility'),subtitle:const Text('Larger text, simpler wording and reduced movement.'),
          trailing:const Icon(Icons.chevron_right),onTap:()=>_open(const AccessibilityScreen()))),
        const SizedBox(height:18),
        SizedBox(height:50,child:OutlinedButton.icon(
          onPressed:_logout,icon:const Icon(Icons.logout),label:const Text('Sign out'))),
      ]);
    }));
}
