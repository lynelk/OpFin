import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/accessibility/accessibility_state.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class AccessibilityScreen extends StatefulWidget{
  const AccessibilityScreen({super.key});
  @override State<AccessibilityScreen> createState()=>_AccessibilityScreenState();
}
class _AccessibilityScreenState extends State<AccessibilityScreen>{
  late AccessibilitySettings _settings;
  bool _saving=false;
  @override void initState(){super.initState();_settings=OpFinAccessibility.settings.value;}
  Future<void> _save() async{
    setState(()=>_saving=true);
    await OpFinAccessibility.update(_settings);

    try{
      final token=await UserSession.getAccessToken();
      if(token==null||token.isEmpty){
        throw Exception('Secure session is required to sync accessibility preferences.');
      }
      final response=await http.patch(Uri.parse('$apiUrl/accessibility-preferences'),headers:{
        'Authorization':'Bearer $token','Accept':'application/json','Content-Type':'application/json'
      },body:jsonEncode({
        'simple_language':_settings.simpleLanguage,'large_text':_settings.largeText,
        'reduced_motion':_settings.reducedMotion,'high_contrast':_settings.highContrast,
        'screen_reader_optimised':true}));
      if(response.statusCode<200||response.statusCode>=300){
        throw Exception('Accessibility preference sync failed.');
      }
      if(mounted)Navigator.pop(context);
    }catch(_){
      if(mounted){
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content:Text('Accessibility settings were saved on this device, but could not be synced to your account.')));
      }
    }finally{
      if(mounted)setState(()=>_saving=false);
    }
  }
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:const Text('Accessibility')),
    body:SafeArea(child:ListView(padding:const EdgeInsets.all(20),children:[
      const Text('Make OpFin easier to use',style:TextStyle(fontSize:24,fontWeight:FontWeight.w800)),
      const SizedBox(height:8),
      const Text('OpFin supports VoiceOver, TalkBack and device text-size settings.'),
      SwitchListTile(contentPadding:EdgeInsets.zero,title:const Text('Larger text'),
        subtitle:const Text('Use at least 125% text size.'),value:_settings.largeText,
        onChanged:(v)=>setState(()=>_settings=_settings.copyWith(largeText:v))),
      SwitchListTile(contentPadding:EdgeInsets.zero,title:const Text('Simple wording'),
        subtitle:const Text('Prefer short, plain instructions.'),value:_settings.simpleLanguage,
        onChanged:(v)=>setState(()=>_settings=_settings.copyWith(simpleLanguage:v))),
      SwitchListTile(contentPadding:EdgeInsets.zero,title:const Text('Reduce movement'),
        subtitle:const Text('Reduce animations where the device supports it.'),value:_settings.reducedMotion,
        onChanged:(v)=>setState(()=>_settings=_settings.copyWith(reducedMotion:v))),
      SwitchListTile(contentPadding:EdgeInsets.zero,title:const Text('High contrast'),
        subtitle:const Text('Use stronger text, focus and control boundaries.'),value:_settings.highContrast,
        onChanged:(v)=>setState(()=>_settings=_settings.copyWith(highContrast:v))),
      const SizedBox(height:20),
      SizedBox(height:52,child:FilledButton(onPressed:_saving?null:_save,
        child:_saving?const CircularProgressIndicator(strokeWidth:2):const Text('Save'))),
    ])));
}
