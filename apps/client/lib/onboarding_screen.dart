import 'package:flutter/material.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/register_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class OnboardingScreen extends StatefulWidget{
  const OnboardingScreen({super.key});
  @override State<OnboardingScreen> createState()=>_OnboardingScreenState();
}
class _OnboardingScreenState extends State<OnboardingScreen>{
  final _controller=PageController();int _index=0;
  final _pages=const[
    _Page(Icons.wallet_outlined,'Know what matters','See what you can borrow, what you owe, and the next action in one place.'),
    _Page(Icons.shield_outlined,'Borrow with clear costs','Review the amount you receive, fees, interest and repayment dates before you accept.'),
  ];
  Future<void> _finish() async{
    final p=await SharedPreferences.getInstance();await p.setBool('seenOnboarding',true);
    if(!mounted)return;Navigator.pushReplacement(context,MaterialPageRoute(builder:(_)=>const RegisterScreen()));
  }
  @override Widget build(BuildContext context)=>Scaffold(
    body:SafeArea(child:Column(children:[
      Align(alignment:Alignment.centerRight,child:TextButton(onPressed:_finish,child:const Text('Skip'))),
      Expanded(child:PageView.builder(controller:_controller,itemCount:_pages.length,
        onPageChanged:(v)=>setState(()=>_index=v),itemBuilder:(context,i){
          final p=_pages[i];return Padding(padding:const EdgeInsets.all(28),child:Column(
            mainAxisAlignment:MainAxisAlignment.center,children:[
              Semantics(excludeSemantics:true,child:Icon(p.icon,size:90,color:OpFinColors.indigo)),
              const SizedBox(height:30),
              Semantics(header:true,child:Text(p.title,textAlign:TextAlign.center,
                style:const TextStyle(fontSize:28,fontWeight:FontWeight.w800))),
              const SizedBox(height:12),
              Text(p.body,textAlign:TextAlign.center,
                style:const TextStyle(fontSize:17,height:1.45,color:OpFinColors.muted)),
            ]));
        })),
      Padding(padding:const EdgeInsets.all(24),child:SizedBox(width:double.infinity,height:52,
        child:FilledButton(onPressed:(){
          if(_index==_pages.length-1){_finish();}else{
            _controller.nextPage(duration:MediaQuery.of(context).disableAnimations?Duration.zero:const Duration(milliseconds:250),
              curve:Curves.easeOut);
          }},child:Text(_index==_pages.length-1?'Get started':'Next')))),
    ])));
}
class _Page{const _Page(this.icon,this.title,this.body);final IconData icon;final String title,body;}
