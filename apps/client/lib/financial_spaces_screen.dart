import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/location_context_screen.dart';
import 'package:opfin/financial_space_statements_screen.dart';
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
          DropdownMenuItem(value:'investment_club',child:Text('Investment club')),
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
  IconData _icon(String? t)=>t=='savings_group'?Icons.groups_outlined:t=='investment_club'?Icons.trending_up_outlined:t=='business'?Icons.storefront_outlined:t=='sacco'?Icons.account_balance_outlined:t=='household'?Icons.home_outlined:Icons.account_balance_wallet_outlined;
  String _label(String? t)=>{'personal':'My money','savings_group':'Savings group','investment_club':'Investment club','business':'Business','sacco':'SACCO','household':'Household','investment_fund':'Investment / fund','partner':'Partner'}[t]??'Organisation';
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
  bool get groupLike=>['savings_group','investment_club','sacco'].contains(widget.space['type']?.toString());
  bool get canManageSpace=>const {'owner','administrator','admin','chairperson','treasurer','secretary','director','manager'}.contains(widget.space['role']?.toString());
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
    if(groupLike)Card(child:ListTile(leading:const Icon(Icons.group_add_outlined),title:const Text('Members & invitations'),subtitle:const Text('Manage membership and roles from the same OpFin identity.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>GroupMembersScreen(spaceId:id))))),
    if(groupLike)Card(child:ListTile(leading:const Icon(Icons.verified_outlined),title:const Text('Registration & verification'),subtitle:const Text('Attach government or authority identifiers without changing the OpFin group identity.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>GroupCredentialsScreen(space:widget.space))))),
    if(groupLike)Card(child:ListTile(leading:const Icon(Icons.health_and_safety_outlined),title:const Text('Group protection'),subtitle:const Text('See approved group-capable insurance products. Enrolment remains controlled.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>GroupProtectionCatalogueScreen(space:widget.space))))),
    if(groupLike)Card(child:ListTile(leading:const Icon(Icons.map_outlined),title:const Text('Operating area'),subtitle:const Text('Record the group or club operating area without exposing member home locations.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>LocationContextScreen(subjectType:'financial_space',subjectId:id,purpose:'group_operating_area',title:'Operating area',description:'Use a locality or district-level location for the group operating area.',countryCode:widget.space['country']?.toString()??'UG',readOnly:!canManageSpace))))),
    if(groupLike)Card(child:ListTile(leading:const Icon(Icons.event_outlined),title:const Text('Meeting place'),subtitle:const Text('Add a location members can recognise and open for directions.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>LocationContextScreen(subjectType:'financial_space',subjectId:id,purpose:'group_meeting_place',title:'Meeting place',description:'This location is visible to authorised members of this Space.',countryCode:widget.space['country']?.toString()??'UG',readOnly:!canManageSpace))))),
    if(['savings_group','investment_club','sacco','business','investment_fund'].contains(widget.space['type']?.toString()))
      Card(child:ListTile(leading:const Icon(Icons.receipt_long_outlined),title:const Text('Treasury & statements'),subtitle:const Text('View treasury balances and issue immutable bank-style OpFin statements.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>FinancialSpaceStatementsScreen(space:widget.space))))),
    Card(child:ListTile(leading:const Icon(Icons.location_city_outlined),title:const Text('Assets & project locations'),subtitle:const Text('Attach locations to property, farm, project or other recorded assets where useful.'),trailing:const Icon(Icons.chevron_right),onTap:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>FinancialAssetLocationsScreen(space:widget.space))))),
  ]));}));
  Widget _tile(String t,String v,VoidCallback tap)=>Card(child:ListTile(title:Text(t),subtitle:Text(v),trailing:const Icon(Icons.add_circle_outline),onTap:tap));
  String _label(String? t)=>t=='savings_group'?'Group money':t=='investment_club'?'Investment club':t=='sacco'?'SACCO':t=='business'?'Business money':t=='personal'?'My money':'Financial space';
}
class GroupMembersScreen extends StatefulWidget{final int spaceId;const GroupMembersScreen({super.key,required this.spaceId});@override State<GroupMembersScreen> createState()=>_GroupMembersScreenState();}
class _GroupMembersScreenState extends State<GroupMembersScreen>{late Future<List<dynamic>> f;@override void initState(){super.initState();f=FinancialSpacesApi.members(widget.spaceId);}Future<void> invite()async{final p=TextEditingController();final ok=await showDialog<bool>(context:context,builder:(c)=>AlertDialog(title:const Text('Invite member'),content:TextField(controller:p,keyboardType:TextInputType.phone,decoration:const InputDecoration(labelText:'Phone number')),actions:[TextButton(onPressed:()=>Navigator.pop(c,false),child:const Text('Cancel')),FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('Invite'))]));if(ok==true&&p.text.isNotEmpty){ await FinancialSpacesApi.invite(widget.spaceId,p.text,'member'); if(mounted){ ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Invitation created.'))); } }}@override Widget build(BuildContext c)=>Scaffold(appBar:AppBar(title:const Text('Group members')),floatingActionButton:FloatingActionButton.extended(onPressed:invite,icon:const Icon(Icons.person_add),label:const Text('Invite')),body:FutureBuilder<List<dynamic>>(future:f,builder:(c,s){if(s.connectionState!=ConnectionState.done)return const Center(child:CircularProgressIndicator());return ListView(padding:const EdgeInsets.all(20),children:(s.data??[]).map((m)=>ListTile(leading:const Icon(Icons.person_outline),title:Text('Member ${m['user_id']}'),subtitle:Text('${m['role']} · ${m['status']}'))).toList());}));}


class GroupCredentialsScreen extends StatefulWidget {
  const GroupCredentialsScreen({super.key, required this.space});
  final Map<String, dynamic> space;

  @override
  State<GroupCredentialsScreen> createState() => _GroupCredentialsScreenState();
}

class _GroupCredentialsScreenState extends State<GroupCredentialsScreen> {
  late Future<List<Map<String, dynamic>>> _credentials;
  int get spaceId => (widget.space['id'] as num).toInt();
  bool get canAdmin => const {
        'owner',
        'administrator',
        'admin',
        'chairperson',
        'treasurer',
        'secretary',
        'director',
        'manager',
      }.contains(widget.space['role']?.toString());

  @override
  void initState() {
    super.initState();
    _credentials = FinancialSpacesApi.credentials(spaceId);
  }

  Future<void> _reload() async {
    setState(() => _credentials = FinancialSpacesApi.credentials(spaceId));
    await _credentials;
  }

  Future<void> _add() async {
    String type = 'government_group_code';
    final issuerCode = TextEditingController();
    final issuerName = TextEditingController();
    final value = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setLocal) => AlertDialog(
          title: const Text('Add registration or identifier'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: type,
                  decoration: const InputDecoration(labelText: 'Identifier type'),
                  items: const [
                    DropdownMenuItem(value: 'government_group_code', child: Text('Government group code')),
                    DropdownMenuItem(value: 'registration_number', child: Text('Registration number')),
                    DropdownMenuItem(value: 'cooperative_registration', child: Text('Cooperative / SACCO registration')),
                    DropdownMenuItem(value: 'tax_identifier', child: Text('Tax identifier')),
                    DropdownMenuItem(value: 'other', child: Text('Other official identifier')),
                  ],
                  onChanged: (v) => setLocal(() => type = v ?? type),
                ),
                TextField(
                  controller: issuerName,
                  decoration: const InputDecoration(labelText: 'Issuing authority'),
                ),
                TextField(
                  controller: issuerCode,
                  decoration: const InputDecoration(labelText: 'Authority code or short name'),
                ),
                TextField(
                  controller: value,
                  decoration: const InputDecoration(labelText: 'Identifier'),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );

    if (ok != true ||
        issuerName.text.trim().isEmpty ||
        issuerCode.text.trim().isEmpty ||
        value.text.trim().isEmpty) {
      return;
    }

    try {
      await FinancialSpacesApi.declareCredential(
        spaceId,
        type: type,
        issuerCode: issuerCode.text.trim(),
        issuerName: issuerName.text.trim(),
        value: value.text.trim(),
        country: widget.space['country']?.toString(),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Identifier saved for verification.')),
      );
      await _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Registration & verification')),
        floatingActionButton: canAdmin
            ? FloatingActionButton.extended(
                onPressed: _add,
                icon: const Icon(Icons.add),
                label: const Text('Add identifier'),
              )
            : null,
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _credentials,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(snapshot.error.toString(), textAlign: TextAlign.center),
                ),
              );
            }

            final credentials = snapshot.data ?? const <Map<String, dynamic>>[];
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text(
                    'Keep official identifiers attached to this group without replacing its permanent OpFin identity.',
                    style: TextStyle(fontSize: 16),
                  ),
                  const SizedBox(height: 12),
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(14),
                      child: Text(
                        'A new government or regulator code can be added and verified here when it becomes applicable. Existing membership and financial history stay intact.',
                      ),
                    ),
                  ),
                  if (credentials.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('No external registration or authority identifier has been recorded yet.'),
                      ),
                    )
                  else
                    ...credentials.map(
                      (credential) => Card(
                        child: ListTile(
                          leading: Icon(
                            credential['verification_status'] == 'verified'
                                ? Icons.verified
                                : Icons.badge_outlined,
                          ),
                          title: Text(credential['credential_value']?.toString() ?? 'Identifier'),
                          subtitle: Text(
                            (credential['issuer_name']?.toString() ?? 'Authority') +
                                ' · ' +
                                (credential['credential_type']?.toString() ?? '').replaceAll('_', ' '),
                          ),
                          trailing: Text(
                            (credential['verification_status']?.toString() ?? 'declared')
                                .replaceAll('_', ' '),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 72),
                ],
              ),
            );
          },
        ),
      );
}

class GroupProtectionCatalogueScreen extends StatefulWidget {
  const GroupProtectionCatalogueScreen({super.key, required this.space});
  final Map<String, dynamic> space;

  @override
  State<GroupProtectionCatalogueScreen> createState() =>
      _GroupProtectionCatalogueScreenState();
}

class _GroupProtectionCatalogueScreenState
    extends State<GroupProtectionCatalogueScreen> {
  late Future<List<Map<String, dynamic>>> _products;
  final NumberFormat _money = NumberFormat('#,##0', 'en_US');
  int get spaceId => (widget.space['id'] as num).toInt();

  @override
  void initState() {
    super.initState();
    _products = FinancialSpacesApi.groupProtectionProducts(spaceId);
  }

  Future<void> _reload() async {
    setState(() => _products = FinancialSpacesApi.groupProtectionProducts(spaceId));
    await _products;
  }

  String _amount(dynamic value, String currency) {
    final minor = value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;
    return currency + ' ' + _money.format(minor);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Group protection')),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _products,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(snapshot.error.toString(), textAlign: TextAlign.center),
                ),
              );
            }

            final products = snapshot.data ?? const <Map<String, dynamic>>[];
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Text(
                    widget.space['name']?.toString() ?? 'Group',
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Approved group-capable products can be reviewed here. Group enrolment and premium collection remain unavailable until member-consent, partner and regulatory controls are activated.',
                  ),
                  const SizedBox(height: 18),
                  if (products.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'No approved group-capable protection product is currently available for this country.',
                        ),
                      ),
                    )
                  else
                    ...products.map(
                      (product) => Card(
                        child: ExpansionTile(
                          leading: const Icon(Icons.health_and_safety_outlined),
                          title: Text(
                            product['name']?.toString() ?? 'Protection product',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          subtitle: Text(
                            (product['insurer_name']?.toString() ?? 'Insurer') +
                                ' · ' +
                                _amount(
                                  product['premium_amount_minor'],
                                  product['currency']?.toString() ?? 'UGX',
                                ) +
                                ' ' +
                                (product['premium_frequency']?.toString() ?? ''),
                          ),
                          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                          children: [
                            Align(
                              alignment: Alignment.centerLeft,
                              child: Text(
                                'Cover limit: ' +
                                    (product['coverage_limit_minor'] == null
                                        ? 'See terms'
                                        : _amount(
                                            product['coverage_limit_minor'],
                                            product['currency']?.toString() ?? 'UGX',
                                          )),
                              ),
                            ),
                            const SizedBox(height: 8),
                            const Align(
                              alignment: Alignment.centerLeft,
                              child: Text(
                                'Review only. Activation requires the approved group insurance operating model.',
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            );
          },
        ),
      );
}


class FinancialAssetLocationsScreen extends StatefulWidget {
  const FinancialAssetLocationsScreen({super.key, required this.space});
  final Map<String, dynamic> space;

  @override
  State<FinancialAssetLocationsScreen> createState() =>
      _FinancialAssetLocationsScreenState();
}

class _FinancialAssetLocationsScreenState
    extends State<FinancialAssetLocationsScreen> {
  late Future<List<Map<String, dynamic>>> _assets;

  int get spaceId => (widget.space['id'] as num).toInt();
  bool get canManageSpace => const {
        'owner',
        'administrator',
        'admin',
        'chairperson',
        'treasurer',
        'secretary',
        'director',
        'manager',
      }.contains(widget.space['role']?.toString());

  @override
  void initState() {
    super.initState();
    _assets = FinancialSpacesApi.assets(spaceId);
  }

  Future<void> _reload() async {
    setState(() => _assets = FinancialSpacesApi.assets(spaceId));
    await _assets;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Assets & project locations')),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _assets,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(snapshot.error.toString()),
                ),
              );
            }

            final assets = snapshot.data ?? const <Map<String, dynamic>>[];
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text(
                    'Map only the assets where location adds real value.',
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Property, farms, project sites and other physical investments can carry a precise risk or project location. Financial assets that do not need a location can remain unmapped.',
                  ),
                  const SizedBox(height: 16),
                  if (assets.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'No assets are recorded in this Financial Space yet.',
                        ),
                      ),
                    )
                  else
                    ...assets.map(
                      (asset) => Card(
                        child: ListTile(
                          leading: const Icon(Icons.location_city_outlined),
                          title: Text(asset['name']?.toString() ?? 'Asset'),
                          subtitle: Text(
                            (asset['asset_type']?.toString() ?? 'asset')
                                .replaceAll('_', ' '),
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => LocationContextScreen(
                                subjectType: 'financial_asset',
                                subjectId: (asset['id'] as num).toInt(),
                                purpose: 'investment_asset_location',
                                title: asset['name']?.toString() ??
                                    'Asset location',
                                description:
                                    'Use precise location only where the asset or investment genuinely depends on a physical site.',
                                countryCode:
                                    widget.space['country']?.toString() ?? 'UG',
                                preciseRecommended: true,
                                readOnly: !canManageSpace,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            );
          },
        ),
      );
}
