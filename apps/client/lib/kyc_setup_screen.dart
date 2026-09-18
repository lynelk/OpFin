import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class KycSetupScreen extends StatefulWidget {
  const KycSetupScreen({super.key});
  @override
  State<KycSetupScreen> createState()=>_KycSetupScreenState();
}

class _KycSetupScreenState extends State<KycSetupScreen> {
  final _nin=TextEditingController();
  final _picker=ImagePicker();
  XFile? _front,_back,_selfie;
  bool _consent=false,_loading=false;

  Future<XFile?> _camera({bool selfie=false})=>_picker.pickImage(
    source:ImageSource.camera,imageQuality:88,
    preferredCameraDevice:selfie?CameraDevice.front:CameraDevice.rear);

  Future<Map<String,String>> _headers() async {
    final token=await UserSession.getAccessToken();
    if(token==null) throw Exception('Please sign in again.');
    return {'Authorization':'Bearer $token','Accept':'application/json'};
  }

  Future<void> _submit() async {
    final nin=_nin.text.trim().toUpperCase();
    if(!RegExp(r'^[A-Z0-9]{14}$').hasMatch(nin)){
      _message('Enter the 14-character NIN on your National ID.');return;
    }
    if(_front==null||_back==null||_selfie==null){
      _message('Take all three photos to continue.');return;
    }
    if(!_consent){_message('Allow the credit check to calculate your loan limit.');return;}
    setState(()=>_loading=true);
    try{
      final token=await UserSession.getAccessToken();
      final consent=await http.post(Uri.parse('$apiUrl/consents'),headers:{
        'Authorization':'Bearer $token','Accept':'application/json',
        'Content-Type':'application/json',
      },body:jsonEncode({
        'purpose':'credit_processing','policy_version':'credit-consent-v1',
        'channel':'app','metadata':{'plain_language':true},
      }));
      final consentBody=jsonDecode(consent.body) as Map<String,dynamic>;
      if(consent.statusCode<200||consent.statusCode>=300||consentBody['success']!=true){
        throw Exception(consentBody['message']?.toString()??'Unable to record consent.');
      }
      final request=http.MultipartRequest('POST',Uri.parse('$apiUrl/kyc/cases'));
      request.headers.addAll(await _headers());
      request.fields['national_id']=nin;
      request.fields['capture_channel']='app';
      request.files.add(await http.MultipartFile.fromPath('national_id_front',_front!.path));
      request.files.add(await http.MultipartFile.fromPath('national_id_back',_back!.path));
      request.files.add(await http.MultipartFile.fromPath('selfie_with_id',_selfie!.path));
      final response=await http.Response.fromStream(await request.send());
      final decoded=jsonDecode(response.body) as Map<String,dynamic>;
      if(response.statusCode<200||response.statusCode>=300||decoded['success']!=true){
        throw Exception(decoded['message']?.toString()??'Unable to verify identity.');
      }
      if(!mounted)return;
      _message(decoded['message']?.toString()??'Identity details received.');
      Navigator.pop(context,true);
    }catch(error){_message(error.toString().replaceFirst('Exception: ',''));}
    finally{if(mounted)setState(()=>_loading=false);}
  }

  void _message(String text){
    if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(text)));
  }

  void _assistance()=>showDialog<void>(context:context,builder:(_)=>AlertDialog(
    title:const Text('Need help with the photos?'),
    content:const Text(
      'Someone you trust may help position the ID or camera. Keep your PIN and OTP private. If camera use is not possible because of a disability or another access need, contact OpFin support for assisted identity verification.'),
    actions:[TextButton(onPressed:()=>Navigator.pop(context),child:const Text('Close'))]));

  Widget _step(int number,String title,String hint,bool done,VoidCallback onTap)=>Semantics(
    button:true,label:'$title. ${done?"Photo captured":"Photo required"}',
    child:Card(child:ListTile(
      minVerticalPadding:14,
      leading:CircleAvatar(child:Text('$number')),
      title:Text(title,style:const TextStyle(fontWeight:FontWeight.w700)),
      subtitle:Text(done?'Photo captured':hint),
      trailing:Icon(done?Icons.check_circle:Icons.camera_alt_outlined,
        color:done?OpFinColors.success:null),
      onTap:_loading?null:onTap)));

  @override
  Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:const Text('Verify your identity')),
    body:SafeArea(child:ListView(padding:const EdgeInsets.all(20),children:[
      const Text('Three photos. One check.',
        style:TextStyle(fontSize:25,fontWeight:FontWeight.w800)),
      const SizedBox(height:8),
      const Text('Use good light. Keep the whole ID visible.'),
      const SizedBox(height:20),
      TextField(controller:_nin,textCapitalization:TextCapitalization.characters,maxLength:14,
        decoration:const InputDecoration(labelText:'NIN',prefixIcon:Icon(Icons.badge_outlined))),
      _step(1,'Front of National ID','Tap to take a photo',_front!=null,() async{
        final x=await _camera();if(x!=null)setState(()=>_front=x);}),
      _step(2,'Back of National ID','Tap to take a photo',_back!=null,() async{
        final x=await _camera();if(x!=null)setState(()=>_back=x);}),
      _step(3,'Photo of you holding the ID','Keep your face and ID visible',_selfie!=null,() async{
        final x=await _camera(selfie:true);if(x!=null)setState(()=>_selfie=x);}),
      TextButton.icon(onPressed:_assistance,icon:const Icon(Icons.accessibility_new),
        label:const Text('I need help with this step')),
      CheckboxListTile(
        value:_consent,onChanged:(v)=>setState(()=>_consent=v==true),
        contentPadding:EdgeInsets.zero,controlAffinity:ListTileControlAffinity.leading,
        title:const Text('Allow OpFin to check my credit information'),
        subtitle:const Text(
          'OpFin may use licensed CRB, mobile-network and approved partner information to calculate your score and loan limit. You can revoke this later.')),
      const SizedBox(height:18),
      SizedBox(height:54,child:FilledButton(
        onPressed:_loading?null:_submit,
        child:_loading?const CircularProgressIndicator(strokeWidth:2)
          :const Text('Verify my identity'))),
    ])));
}
