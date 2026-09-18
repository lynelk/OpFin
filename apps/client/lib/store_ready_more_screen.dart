import 'package:flutter/material.dart';
import 'package:opfin/account_delete_screen.dart';
import 'package:opfin/accessibility_screen.dart';
import 'package:opfin/faq_screen.dart';
import 'package:opfin/profile_screen.dart';
import 'package:opfin/term_variations_screen.dart';
import 'package:opfin/wallets_screen.dart';

class StoreReadyMoreMobileScreen extends StatelessWidget{
  const StoreReadyMoreMobileScreen({super.key});
  @override Widget build(BuildContext context)=>ListView(
    padding:const EdgeInsets.all(20),children:[
      const Text('More',style:TextStyle(fontSize:27,fontWeight:FontWeight.w800)),
      const SizedBox(height:8),
      const Text('Account, help and privacy. Extra financial products stay out of the way until they are ready for you.'),
      const SizedBox(height:20),
      _Tile(Icons.person_outline,'Profile & security','Identity, phones and sign-in.',
        ()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const ProfileScreen()))),
      _Tile(Icons.account_balance_wallet_outlined,'Wallets','Choose where payouts and repayments happen.',
        ()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const WalletsScreen()))),
      _Tile(Icons.accessibility_new,'Accessibility','Larger text, simple wording and reduced movement.',
        ()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const AccessibilityScreen()))),
      _Tile(Icons.help_outline,'Help & support','Get help without sharing your PIN or OTP.',
        ()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const FaqsScreen()))),
      _Tile(Icons.rule_outlined,'Changes to loan terms','Review and consent to any proposed credit-term variation.',
        ()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const TermVariationsScreen()))),
      const SizedBox(height:18),
      const Text('Privacy & account',style:TextStyle(fontSize:18,fontWeight:FontWeight.w700)),
      const Card(child:Padding(padding:EdgeInsets.all(14),
        child:SelectableText('Privacy policy: https://opfin-production.up.railway.app/privacy-policy'))),
      _Tile(Icons.delete_outline,'Delete account','Close your account and review required record retention.',
        ()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const AccountDeleteScreen()))),
    ]);
}

class _Tile extends StatelessWidget{
  const _Tile(this.icon,this.title,this.subtitle,this.tap);
  final IconData icon;final String title,subtitle;final VoidCallback tap;
  @override Widget build(BuildContext context)=>Card(child:ListTile(
    minVerticalPadding:12,leading:Icon(icon),
    title:Text(title,style:const TextStyle(fontWeight:FontWeight.w700)),
    subtitle:Text(subtitle),trailing:const Icon(Icons.chevron_right),onTap:tap));
}
