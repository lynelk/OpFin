import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/credit_offers_screen.dart';
import 'package:opfin/financial_spaces_screen.dart';
import 'package:opfin/connected_financial_life_screen.dart';
import 'package:opfin/kyc_setup_screen.dart';
import 'package:opfin/loan_application_screen.dart';
import 'package:opfin/loan_applications_screen.dart';
import 'package:opfin/loan_repayment_screen.dart';
import 'package:opfin/profile_screen.dart';
import 'package:opfin/receipts_screen.dart';
import 'package:opfin/secondary_phone_screen.dart';
import 'package:opfin/services/credit_profile_api.dart';
import 'package:opfin/store_ready_more_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override
  State<HomeScreen> createState()=>_HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen>{
  int _index=0;
  @override
  Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:const Text('OpFin'),actions:[IconButton(tooltip:'My spaces',icon:const Icon(Icons.swap_horiz),onPressed:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const FinancialSpacesScreen())))]),
    body:IndexedStack(index:_index,children:const[
      _HomePage(),_BorrowPage(),_ActivityPage(),StoreReadyMoreMobileScreen()
    ]),
    bottomNavigationBar:NavigationBar(
      selectedIndex:_index,onDestinationSelected:(v)=>setState(()=>_index=v),
      destinations:const[
        NavigationDestination(icon:Icon(Icons.home_outlined),selectedIcon:Icon(Icons.home),label:'Home'),
        NavigationDestination(icon:Icon(Icons.account_balance_wallet_outlined),selectedIcon:Icon(Icons.account_balance_wallet),label:'Borrow'),
        NavigationDestination(icon:Icon(Icons.receipt_long_outlined),selectedIcon:Icon(Icons.receipt_long),label:'Activity'),
        NavigationDestination(icon:Icon(Icons.more_horiz),selectedIcon:Icon(Icons.more),label:'More'),
      ]),
  );
}

class _HomePage extends StatefulWidget{
  const _HomePage();
  @override State<_HomePage> createState()=>_HomePageState();
}
class _HomePageState extends State<_HomePage>{
  late Future<Map<String,dynamic>> _state;
  String _name='there';
  final _money=NumberFormat('#,##0','en_US');

  @override void initState(){super.initState();_state=CreditProfileApi.load();_loadName();}
  Future<void> _loadName() async{
    final p=await SharedPreferences.getInstance();
    if(mounted)setState(()=>_name=(p.getString('name')??'there').split(' ').first);
  }
  int _n(dynamic v)=>v is num?v.toInt():int.tryParse('$v')??0;
  String _ugx(dynamic v)=>'UGX ${_money.format(_n(v))}';
  Future<void> _reload() async{setState(()=>_state=CreditProfileApi.load());await _state;}
  Future<void> _open(Widget page) async{
    await Navigator.push(context,MaterialPageRoute(builder:(_)=>page));
    await _reload();
  }

  Future<void> _next(Map<String,dynamic> data) async{
    final next=(data['next_action'] as Map?)?.cast<String,dynamic>()??{};
    final code=next['code']?.toString()??'';
    if(code=='VERIFY_IDENTITY'||code=='GRANT_CREDIT_CONSENT'){
      await _open(const KycSetupScreen());return;
    }
    if(code=='CALCULATE_PROFILE'){
      setState(()=>_state=CreditProfileApi.refresh());await _state;return;
    }
    if(code=='BORROW'){await _open(const LoanApplicationScreen());return;}
    if(code=='REPAY'){
      final loan=(data['active_loan'] as Map?)?.cast<String,dynamic>();
      if(loan!=null){
        await _open(LoanRepaymentScreen(
          loanId:_n(loan['id']),
          repaymentAmount:_n(loan['outstanding_minor'])));
        return;
      }
    }
    await _open(const ProfileScreen());
  }

  void _scoreDetails(Map<String,dynamic> profile){
    final components=(profile['component_breakdown'] as Map?)?.cast<String,dynamic>()??{};
    showModalBottomSheet<void>(context:context,isScrollControlled:true,builder:(_)=>SafeArea(
      child:Padding(padding:const EdgeInsets.all(24),child:Column(
        mainAxisSize:MainAxisSize.min,crossAxisAlignment:CrossAxisAlignment.start,children:[
          const Text('What makes up my score?',style:TextStyle(fontSize:22,fontWeight:FontWeight.w800)),
          const SizedBox(height:8),
          const Text('These parts combine into your OpFin Score. Missing partner data does not become a made-up score.'),
          const SizedBox(height:16),
          if(components.isEmpty)const Text('Score details are still being collected.'),
          ...components.entries.map((e){
            final d=(e.value as Map?)?.cast<String,dynamic>()??{};
            final label={'crb':'Credit history','mno':'Mobile activity','third_party':'Approved partner data','internal':'OpFin behaviour'}[e.key]??e.key;
            return ListTile(contentPadding:EdgeInsets.zero,title:Text(label),
              trailing:Text('${(d['score'] as num?)?.round()??0}/100',
                style:const TextStyle(fontWeight:FontWeight.w700)));
          }),
        ]))));
  }

  @override Widget build(BuildContext context)=>RefreshIndicator(
    onRefresh:_reload,
    child:FutureBuilder<Map<String,dynamic>>(future:_state,builder:(context,s){
      if(s.connectionState!=ConnectionState.done){
        return ListView(children:const [SizedBox(height:260),Center(child:CircularProgressIndicator())]);
      }
      if(s.hasError){
        return ListView(padding:const EdgeInsets.all(24),children:[
          const SizedBox(height:100),
          const Icon(Icons.cloud_off_outlined,size:52),
          const SizedBox(height:12),
          const Text('We could not load your account right now.',textAlign:TextAlign.center),
          const SizedBox(height:12),
          FilledButton(onPressed:_reload,child:const Text('Try again')),
        ]);
      }
      final data=s.data??{};
      final profile=(data['profile'] as Map?)?.cast<String,dynamic>()??{};
      final setup=(data['setup'] as Map?)?.cast<String,dynamic>()??{};
      final next=(data['next_action'] as Map?)?.cast<String,dynamic>()??{};
      final pending=profile['status']?.toString()=='pending'||profile.isEmpty;
      final amountDue=_n(profile['amount_due_minor']);
      final available=_n(profile['available_to_borrow_minor']);
      final score=(profile['composite_score'] as num?)?.round();
      return ListView(padding:const EdgeInsets.all(20),children:[
        Semantics(header:true,child:Text('Hi, $_name',
          style:const TextStyle(fontSize:27,fontWeight:FontWeight.w800))),
        const SizedBox(height:4),
        Text(pending?'One step at a time. Build your financial picture as you go.'
          :amountDue>0?'Your repayment comes first.':'Understand, manage, plan and improve your money.',
          style:const TextStyle(color:OpFinColors.muted)),
        const SizedBox(height:18),
        Card(
          child:Padding(padding:const EdgeInsets.all(20),child:Column(
            crossAxisAlignment:CrossAxisAlignment.start,children:[
              Text(amountDue>0?'Amount due':pending?'Loan limit':'Available to borrow',
                style:const TextStyle(color:OpFinColors.muted)),
              const SizedBox(height:5),
              Text(amountDue>0?_ugx(amountDue):pending?'Not ready yet':_ugx(available),
                style:const TextStyle(fontSize:32,fontWeight:FontWeight.w800)),
              if(!pending&&score!=null)...[
                const SizedBox(height:12),
                Row(children:[
                  Expanded(child:Text('OpFin Score  $score / 100 · ${profile['band']??''}',
                    style:const TextStyle(fontWeight:FontWeight.w700))),
                  TextButton(onPressed:()=>_scoreDetails(profile),child:const Text('Details')),
                ]),
              ],
              if(_n(profile['total_outstanding_minor'])>0)...[
                const SizedBox(height:8),
                Text('Total outstanding: ${_ugx(profile['total_outstanding_minor'])}'),
                if(profile['next_due_date']!=null)
                  Text('Next payment date: ${profile['next_due_date']}'),
              ],
              const SizedBox(height:18),
              SizedBox(width:double.infinity,height:50,child:FilledButton(
                onPressed:()=>_next(data),
                child:Text(next['label']?.toString()??'Continue'))),
            ])),
        ),
        const SizedBox(height:14),
        Card(child:ListTile(
          leading:const Icon(Icons.account_balance_wallet_outlined),
          title:const Text('My money & spaces',style:TextStyle(fontWeight:FontWeight.w700)),
          subtitle:const Text('Personal money, savings groups, household and organisations in one place.'),
          trailing:const Icon(Icons.chevron_right),
          onTap:()=>_open(const FinancialSpacesScreen()))),
        Card(child:ListTile(
          leading:const Icon(Icons.insights_outlined),
          title:const Text('Plan my financial life',style:TextStyle(fontWeight:FontWeight.w700)),
          subtitle:const Text('Accounts, goals, household, business and community context.'),
          trailing:const Icon(Icons.chevron_right),
          onTap:()=>_open(const ConnectedFinancialLifeScreen()))),
        if(setup['secondary_phone_verified']!=true)
          Card(child:ListTile(
            leading:const Icon(Icons.add_call),
            title:const Text('Add another phone (optional)',style:TextStyle(fontWeight:FontWeight.w700)),
            subtitle:const Text('It can strengthen your profile and add another wallet.'),
            trailing:const Icon(Icons.chevron_right),
            onTap:()=>_open(const SecondaryPhoneScreen()))),
        Card(child:ListTile(
          leading:const Icon(Icons.security_outlined),
          title:const Text('Your setup'),
          subtitle:Text('Phone ✓  ·  Identity ${setup['kyc_status']=='verified'?'✓':'not yet'}'),
          trailing:const Icon(Icons.chevron_right),
          onTap:()=>_open(const ProfileScreen()))),
      ]);
    }),
  );
}

class _BorrowPage extends StatefulWidget{
  const _BorrowPage();
  @override State<_BorrowPage> createState()=>_BorrowPageState();
}
class _BorrowPageState extends State<_BorrowPage>{
  late Future<Map<String,dynamic>> _state;
  final _money=NumberFormat('#,##0','en_US');
  @override void initState(){super.initState();_state=CreditProfileApi.load();}
  int _n(dynamic v)=>v is num?v.toInt():int.tryParse('$v')??0;
  @override Widget build(BuildContext context)=>FutureBuilder<Map<String,dynamic>>(
    future:_state,builder:(context,s){
      if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());
      final data=s.data??{};
      final p=(data['profile'] as Map?)?.cast<String,dynamic>()??{};
      final available=_n(p['available_to_borrow_minor']);
      return ListView(padding:const EdgeInsets.all(20),children:[
        const Text('Borrow',style:TextStyle(fontSize:27,fontWeight:FontWeight.w800)),
        const SizedBox(height:8),
        const Text('See your limit first. Review every cost before accepting a loan.'),
        const SizedBox(height:20),
        Card(child:Padding(padding:const EdgeInsets.all(18),child:Column(
          crossAxisAlignment:CrossAxisAlignment.start,children:[
            const Text('Available loan limit',style:TextStyle(color:OpFinColors.muted)),
            Text('UGX ${_money.format(available)}',
              style:const TextStyle(fontSize:30,fontWeight:FontWeight.w800)),
            const SizedBox(height:16),
            SizedBox(width:double.infinity,child:FilledButton(
              onPressed:available>0?()=>Navigator.push(context,
                MaterialPageRoute(builder:(_)=>const LoanApplicationScreen())):null,
              child:const Text('Apply for a loan'))),
          ]))),
        Card(child:ListTile(
          leading:const Icon(Icons.list_alt_outlined),
          title:const Text('My loan requests'),
          trailing:const Icon(Icons.chevron_right),
          onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const LoanApplicationsScreen())))),
      ]);
    });
}

class _ActivityPage extends StatelessWidget{
  const _ActivityPage();
  @override Widget build(BuildContext context)=>ListView(
    padding:const EdgeInsets.all(20),children:[
      const Text('Activity',style:TextStyle(fontSize:27,fontWeight:FontWeight.w800)),
      const SizedBox(height:8),
      const Text('Loan requests, offers and repayments in one place.'),
      const SizedBox(height:20),
      Card(child:ListTile(
        leading:const Icon(Icons.assignment_outlined),
        title:const Text('Loan requests'),subtitle:const Text('See what is being checked or approved.'),
        trailing:const Icon(Icons.chevron_right),
        onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const LoanApplicationsScreen())))),
      Card(child:ListTile(
        leading:const Icon(Icons.description_outlined),
        title:const Text('Credit offers'),subtitle:const Text('Review costs before accepting.'),
        trailing:const Icon(Icons.chevron_right),
        onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const CreditOffersScreen())))),
      Card(child:ListTile(
        leading:const Icon(Icons.receipt_long_outlined),
        title:const Text('Receipts'),subtitle:const Text('Completed disbursement and repayment acknowledgements.'),
        trailing:const Icon(Icons.chevron_right),
        onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>const ReceiptsScreen())))),
    ]);
}
