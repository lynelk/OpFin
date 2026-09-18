import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';
import 'package:sms_autofill/sms_autofill.dart';

class SecondaryPhoneScreen extends StatefulWidget{
  const SecondaryPhoneScreen({super.key});
  @override State<SecondaryPhoneScreen> createState()=>_SecondaryPhoneScreenState();
}
class _SecondaryPhoneScreenState extends State<SecondaryPhoneScreen>{
  final _phone=TextEditingController(),_otp=TextEditingController();
  String? _normalised;
  bool _codeSent=false,_loading=false;
  String _format(String v){
    final p=v.trim().replaceAll(' ','');
    if(p.startsWith('+256'))return p.substring(1);
    if(p.startsWith('256'))return p;
    return '256${p.substring(1)}';
  }
  void _message(String s){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(s)));}
  Future<void> _send() async{
    final raw=_phone.text.trim().replaceAll(' ','');
    if(!RegExp(r'^(0\d{9}|256\d{9}|\+256\d{9})$').hasMatch(raw)){_message('Enter a valid phone number.');return;}
    setState(()=>_loading=true);
    try{
      String sig='';if(Platform.isAndroid)sig=await SmsAutoFill().getAppSignature;
      _normalised=_format(raw);
      final r=await http.post(Uri.parse('$apiUrl/generate-otp'),body:{
        'phone':_normalised!,if(sig.isNotEmpty)'app_signature':sig});
      final d=jsonDecode(r.body) as Map<String,dynamic>;
      if(r.statusCode!=200||d['success']!=true)throw Exception(d['message']??'Unable to send code.');
      setState(()=>_codeSent=true);
    }catch(e){_message(e.toString().replaceFirst('Exception: ',''));}
    finally{if(mounted)setState(()=>_loading=false);}
  }
  Future<void> _verify() async{
    if(!RegExp(r'^\d{6}$').hasMatch(_otp.text)){_message('Enter the 6-digit code.');return;}
    setState(()=>_loading=true);
    try{
      final v=await http.post(Uri.parse('$apiUrl/verify-otp'),body:{'phone':_normalised!,'otp':_otp.text});
      final vd=jsonDecode(v.body) as Map<String,dynamic>;
      if(v.statusCode!=200||vd['success']!=true)throw Exception(vd['message']??'Code did not work.');
      final verification=((vd['data'] as Map?)?['verification_token'])?.toString();
      final access=await UserSession.getAccessToken();
      final a=await http.post(Uri.parse('$apiUrl/phone-numbers/secondary'),headers:{
        'Authorization':'Bearer $access','Accept':'application/json'
      },body:{'phone':_normalised!,'verification_token':verification});
      final ad=jsonDecode(a.body) as Map<String,dynamic>;
      if(a.statusCode<200||a.statusCode>=300||ad['success']!=true)throw Exception(ad['message']??'Unable to add phone.');
      if(!mounted)return;_message('Second phone verified.');Navigator.pop(context,true);
    }catch(e){_message(e.toString().replaceFirst('Exception: ',''));}
    finally{if(mounted)setState(()=>_loading=false);}
  }
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:const Text('Add another phone')),
    body:SafeArea(child:ListView(padding:const EdgeInsets.all(24),children:[
      const Text('Optional',style:TextStyle(fontWeight:FontWeight.w700)),
      const SizedBox(height:6),
      const Text('Add another phone',style:TextStyle(fontSize:25,fontWeight:FontWeight.w800)),
      const SizedBox(height:8),
      const Text('A second verified number can strengthen your profile and gives you another wallet option. You can still use OpFin with one phone.'),
      const SizedBox(height:24),
      TextField(controller:_phone,enabled:!_codeSent,keyboardType:TextInputType.phone,
        decoration:const InputDecoration(labelText:'Second phone number')),
      if(_codeSent)...[
        const SizedBox(height:18),
        TextField(
          controller:_otp,
          keyboardType:TextInputType.number,
          maxLength:6,
          autofillHints:const [AutofillHints.oneTimeCode],
          decoration:const InputDecoration(
            labelText:'Verification code',
            hintText:'6 digits',
            border:OutlineInputBorder(),
          ),
        ),
      ],
      const SizedBox(height:24),
      SizedBox(height:52,child:FilledButton(
        onPressed:_loading?null:(_codeSent?_verify:_send),
        child:_loading?const CircularProgressIndicator(strokeWidth:2)
          :Text(_codeSent?'Verify and add':'Send code'))),
    ])));
}
