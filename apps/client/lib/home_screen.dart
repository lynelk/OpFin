import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/brand/brand_colors.dart';
import 'package:opfin/credit_offers_screen.dart';
import 'package:opfin/financial_spaces_screen.dart';
import 'package:opfin/financial_hubs.dart';
import 'package:opfin/protection_screen.dart';
import 'package:opfin/personal_money_screen.dart';
import 'package:opfin/kyc_setup_screen.dart';
import 'package:opfin/inclusive_finance_screen.dart';
import 'package:opfin/loan_application_screen.dart';
import 'package:opfin/loan_applications_screen.dart';
import 'package:opfin/loan_repayment_screen.dart';
import 'package:opfin/profile_screen.dart';
import 'package:opfin/receipts_screen.dart';
import 'package:opfin/secondary_phone_screen.dart';
import 'package:opfin/services/credit_profile_api.dart';
import 'package:opfin/services/personal_home_api.dart';
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

  @override void initState(){super.initState();_state=PersonalHomeApi.load();_loadName();}

  Future<void> _loadName() async{
    final p=await SharedPreferences.getInstance();
    if(mounted)setState(()=>_name=(p.getString('name')??'there').split(' ').first);
  }

  int _n(dynamic v)=>v is num?v.toInt():int.tryParse('$v')??0;
  String _ugx(dynamic v)=>'UGX '+_money.format(_n(v));

  Future<void> _reload() async{
    setState(()=>_state=PersonalHomeApi.load());
    await _state;
  }

  Future<void> _open(Widget page) async{
    await Navigator.push(context,MaterialPageRoute(builder:(_)=>page));
    await _reload();
  }

  Future<void> _openSavings() async{
    await _open(Scaffold(
      appBar:AppBar(title:const Text('Savings & goals')),
      body:const SaveMobileScreen(),
    ));
  }

  Future<void> _next(Map<String,dynamic> next,Map<String,dynamic> credit) async{
    final code=next['code']?.toString()??'';
    if(code=='loan_due'){
      final loan=(credit['active_loan'] as Map?)?.cast<String,dynamic>();
      if(loan!=null){
        await _open(LoanRepaymentScreen(
          loanId:_n(loan['id']),
          repaymentAmount:_n(loan['outstanding_minor'])));
        return;
      }
    }
    if(code=='build_goal'){
      await _openSavings();
      return;
    }
    await _open(const PersonalMoneyScreen());
  }

  Future<void> _handleCredit(Map<String,dynamic> credit) async {
    final profile=(credit['profile'] as Map?)?.cast<String,dynamic>()??{};
    final next=(credit['next_action'] as Map?)?.cast<String,dynamic>()??{};
    final amountDue=_n(profile['amount_due_minor']);
    final activeLoan=(credit['active_loan'] as Map?)?.cast<String,dynamic>();

    if(amountDue>0){
      if(activeLoan!=null){
        await _open(LoanRepaymentScreen(
          loanId:_n(activeLoan['id']),
          repaymentAmount:amountDue));
      }else{
        await _open(const LoanApplicationsScreen());
      }
      return;
    }

    switch(next['code']?.toString()??''){
      case 'CALCULATE_PROFILE':
        await CreditProfileApi.refresh();
        await _reload();
        return;
      case 'VERIFY_IDENTITY':
      case 'GRANT_CREDIT_CONSENT':
        await _open(const KycSetupScreen());
        return;
      case 'BORROW':
        await _open(const LoanApplicationScreen());
        return;
      case 'REPAY':
        if(activeLoan!=null){
          await _open(LoanRepaymentScreen(
            loanId:_n(activeLoan['id']),
            repaymentAmount:_n(activeLoan['outstanding_minor'])));
          return;
        }
        await _open(const LoanApplicationsScreen());
        return;
      default:
        await _open(const ProfileScreen());
    }
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
          const Text('We could not load your financial picture right now.',textAlign:TextAlign.center),
          const SizedBox(height:12),
          FilledButton(onPressed:_reload,child:const Text('Try again')),
        ]);
      }

      final data=s.data??{};
      final compass=(data['compass'] as Map?)?.cast<String,dynamic>()??{};
      final position=(compass['position'] as Map?)?.cast<String,dynamic>()??{};
      final cashFlow=(compass['cash_flow'] as Map?)?.cast<String,dynamic>()??{};
      final next=(compass['next_best_action'] as Map?)?.cast<String,dynamic>()??{};
      final credit=(data['credit'] as Map?)?.cast<String,dynamic>()??{};
      final profile=(credit['profile'] as Map?)?.cast<String,dynamic>()??{};
      final setup=(credit['setup'] as Map?)?.cast<String,dynamic>()??{};
      final policies=(data['policies'] as List? ?? const []);
      final spaces=(data['spaces'] as List? ?? const []);
      final availability=(data['availability'] as Map?)?.cast<String,dynamic>()??{};
      final creditAvailable=availability['credit']==true;
      final activePolicies=policies.where((p)=>p is Map&&p['status']=='active').length;
      final otherSpaces=spaces.where((x)=>x is Map&&x['type']!='personal').length;
      final available=position['available_money_minor'];
      final safe=position['safe_to_spend_minor'];
      final debt=_n(position['debt_obligations_minor']);
      final savings=_n(position['current_savings_minor']);
      final upcoming=_n(position['upcoming_obligations_minor']);
      final income=_n(cashFlow['income_minor']);
      final expenses=_n(cashFlow['expense_minor']);
      final creditReady=profile.isNotEmpty&&profile['status']?.toString()!='pending';
      final availableCredit=_n(profile['available_to_borrow_minor']);
      final amountDue=_n(profile['amount_due_minor']);
      final creditNext=(credit['next_action'] as Map?)?.cast<String,dynamic>()??{};

      return ListView(padding:const EdgeInsets.all(20),children:[
        Semantics(header:true,child:Text('Good morning, $_name',
          style:const TextStyle(fontSize:27,fontWeight:FontWeight.w800))),
        const SizedBox(height:4),
        const Text('Your financial life today.',style:TextStyle(color:OpFinColors.muted)),
        const SizedBox(height:18),

        Card(
          child:Padding(
            padding:const EdgeInsets.all(20),
            child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
              Text(safe==null?'Available money':'Safe to spend',
                style:const TextStyle(color:OpFinColors.muted)),
              const SizedBox(height:5),
              Text(
                safe!=null?_ugx(safe):available!=null?_ugx(available):'Add your balances',
                style:const TextStyle(fontSize:32,fontWeight:FontWeight.w800)),
              const SizedBox(height:8),
              Text(
                safe==null
                  ?'Record the money you can actually use so OpFin can plan without guessing.'
                  :'After confirmed and scheduled obligations over the next 30 days.'),
              const SizedBox(height:18),
              Row(children:[
                Expanded(child:_HomeStat(label:'Savings',value:_ugx(savings))),
                const SizedBox(width:8),
                Expanded(child:_HomeStat(label:'Debt',value:_ugx(debt))),
                const SizedBox(width:8),
                Expanded(child:_HomeStat(label:'Coming up',value:_ugx(upcoming))),
              ]),
            ]),
          ),
        ),

        if(next.isNotEmpty)
          Card(
            child:ListTile(
              leading:const Icon(Icons.auto_awesome_outlined),
              title:Text(next['title']?.toString()??'Review your money',
                style:const TextStyle(fontWeight:FontWeight.w700)),
              subtitle:Text(next['text']?.toString()??''),
              trailing:const Icon(Icons.chevron_right),
              onTap:()=>_next(next,credit),
            ),
          ),

        const SizedBox(height:12),
        const Text('My financial life',style:TextStyle(fontSize:19,fontWeight:FontWeight.w700)),
        const SizedBox(height:6),

        Card(child:ListTile(
          leading:const Icon(Icons.insights_outlined),
          title:const Text('Plan my money',style:TextStyle(fontWeight:FontWeight.w700)),
          subtitle:Text('This month: '+_ugx(income)+' in · '+_ugx(expenses)+' out. Plan cash flow, debts and upcoming obligations.'),
          trailing:const Icon(Icons.chevron_right),
          onTap:()=>_open(const PersonalMoneyScreen()))),

        Card(child:ListTile(
          leading:const Icon(Icons.savings_outlined),
          title:const Text('Savings & goals',style:TextStyle(fontWeight:FontWeight.w700)),
          subtitle:Text(savings>0?'You have '+_ugx(savings)+' in partner-confirmed savings.':'Build an emergency fund or another goal at your pace.'),
          trailing:const Icon(Icons.chevron_right),
          onTap:_openSavings)),

        Card(child:ListTile(
          leading:const Icon(Icons.health_and_safety_outlined),
          title:const Text('Protection',style:TextStyle(fontWeight:FontWeight.w700)),
          subtitle:Text(activePolicies>0
            ?activePolicies.toString()+' active protection '+(activePolicies==1?'policy.':'policies.')
            :'Review approved insurance and protection when it is useful to you.'),
          trailing:const Icon(Icons.chevron_right),
          onTap:()=>_open(const ProtectionScreen()))),

        Card(child:ListTile(
          leading:const Icon(Icons.groups_outlined),
          title:const Text('My spaces',style:TextStyle(fontWeight:FontWeight.w700)),
          subtitle:Text(otherSpaces>0
            ?otherSpaces.toString()+' group or organisation '+(otherSpaces==1?'space':'spaces')+' connected to your OpFin identity.'
            :'Saving groups, investment clubs, SACCOs and other relationships appear here.'),
          trailing:const Icon(Icons.chevron_right),
          onTap:()=>_open(const FinancialSpacesScreen()))),

        const SizedBox(height:12),
        const Text('Credit when you need it',style:TextStyle(fontSize:19,fontWeight:FontWeight.w700)),
        const SizedBox(height:6),
        Card(child:ListTile(
          leading:Icon(amountDue>0?Icons.warning_amber_rounded:Icons.account_balance_wallet_outlined),
          title:Text(!creditAvailable
            ?'Credit temporarily unavailable'
            :amountDue>0
              ?'Amount due '+_ugx(amountDue)
              :creditReady
                ?'Available credit '+_ugx(availableCredit)
                :(creditNext['label']?.toString()??'Build your credit profile'),
            style:const TextStyle(fontWeight:FontWeight.w700)),
          subtitle:Text(!creditAvailable
            ?'Your personal money view remains available while the credit service recovers.'
            :amountDue>0
              ?'Repayment comes before another loan request.'
              :'Credit is one financial tool. Review affordability and every cost before borrowing.'),
          trailing:creditAvailable?const Icon(Icons.chevron_right):null,
          onTap:!creditAvailable?null:()=>_handleCredit(credit))),

        if(creditAvailable&&setup['kyc_status']!='verified')
          Card(child:ListTile(
            leading:const Icon(Icons.verified_user_outlined),
            title:const Text('Complete identity verification',style:TextStyle(fontWeight:FontWeight.w700)),
            subtitle:const Text('Verify progressively when a financial service needs it.'),
            trailing:const Icon(Icons.chevron_right),
            onTap:()=>_open(const KycSetupScreen()))),

        if(creditAvailable&&setup['secondary_phone_verified']!=true)
          Card(child:ListTile(
            leading:const Icon(Icons.add_call),
            title:const Text('Add another phone (optional)',style:TextStyle(fontWeight:FontWeight.w700)),
            subtitle:const Text('Useful for another verified wallet, but never required for baseline access.'),
            trailing:const Icon(Icons.chevron_right),
            onTap:()=>_open(const SecondaryPhoneScreen()))),
      ]);
    }),
  );
}

class _HomeStat extends StatelessWidget{
  const _HomeStat({required this.label,required this.value});
  final String label,value;
  @override Widget build(BuildContext context)=>Column(
    crossAxisAlignment:CrossAxisAlignment.start,
    children:[
      Text(label,style:const TextStyle(color:OpFinColors.muted,fontSize:12)),
      const SizedBox(height:3),
      Text(value,style:const TextStyle(fontWeight:FontWeight.w700)),
    ]);
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
      final due=_n(p['amount_due_minor']);
      final loan=(data['active_loan'] as Map?)?.cast<String,dynamic>();
      return ListView(padding:const EdgeInsets.all(20),children:[
        const Text('Borrow',style:TextStyle(fontSize:27,fontWeight:FontWeight.w800)),
        const SizedBox(height:8),
        const Text('See your limit first. Review every cost before accepting a loan.'),
        const SizedBox(height:20),
        Card(child:Padding(padding:const EdgeInsets.all(18),child:Column(
          crossAxisAlignment:CrossAxisAlignment.start,children:[
            Text(due>0?'Amount due':'Available loan limit',style:const TextStyle(color:OpFinColors.muted)),
            Text('UGX ${_money.format(due>0?due:available)}',
              style:const TextStyle(fontSize:30,fontWeight:FontWeight.w800)),
            const SizedBox(height:16),
            SizedBox(width:double.infinity,child:FilledButton(
              onPressed:due>0
                ?(loan==null?null:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>LoanRepaymentScreen(
                    loanId:_n(loan['id']),repaymentAmount:due))))
                :available>0?()=>Navigator.push(context,
                    MaterialPageRoute(builder:(_)=>const LoanApplicationScreen())):null,
              child:Text(due>0?'Repay amount due':'Apply for a loan'))),
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
