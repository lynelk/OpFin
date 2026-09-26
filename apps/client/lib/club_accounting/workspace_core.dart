import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:opfin/services/club_accounting_api.dart';
import 'contracts.dart';
import 'fields.dart';

class ClubAccountingScreen extends StatefulWidget {
  const ClubAccountingScreen({super.key, required this.space, this.gateway});
  final Map<String, dynamic> space;
  final ClubAccountingGateway? gateway;
  @override State<ClubAccountingScreen> createState() => _ClubAccountingScreenState();
}

class _ClubAccountingScreenState extends State<ClubAccountingScreen> {
  late final ClubAccountingGateway api;
  final storage = const FlutterSecureStorage();
  final from = TextEditingController(), through = TextEditingController(text: clubToday());
  List<Map<String, dynamic>> books = [], operations = [];
  Map<String, dynamic> catalogue = {}, result = {};
  int? bookId, userId;
  bool canMake = false, canCheck = false, ownOnly = true, busy = false;
  String message = 'Loading your authorised accounting books…';
  String view = 'report';
  int page = 1;
  bool more = false;
  int get spaceId => ClubAccountingApi.positiveId(widget.space['id']);
  Map<String, dynamic>? get book {
    for (final row in books) { if (row['id'] == bookId) return row; }
    return null;
  }

  @override void initState() { super.initState(); api = widget.gateway ?? ClubAccountingApi(); _load(); }
  @override void dispose() {
    from.dispose(); through.dispose();
    if (widget.gateway == null && api is ClubAccountingApi) (api as ClubAccountingApi).close();
    super.dispose();
  }
  Future<Map<String, dynamic>> request(String action, {Map<String, dynamic> input = const {}, int? target}) =>
      api.call(action, spaceId, bookId: bookId, input: input, targetId: target);
  Future<void> run(Future<void> Function() task) async {
    if (busy) return;
    setState(() => busy = true);
    try { await task(); }
    catch (error) { if (mounted) setState(() => message = error is ClubApiException ? error.message : error is FormatException ? error.message.toString() : 'The result is uncertain. Check the original request before creating another.'); }
    finally { if (mounted) setState(() => busy = false); }
  }
  Future<void> _load() async {
    try {
      final responses = await Future.wait([api.call('books', spaceId), api.call('schema', spaceId), api.call('profile', spaceId)]);
      if (!mounted) return;
      setState(() {
        books = clubRows(responses[0]['books']); operations = clubRows(responses[1]['operations']);
        canMake = responses[0]['can_make'] == true; canCheck = responses[0]['can_check'] == true;
        userId = (responses[2]['user'] as Map?)?['id'] as int?;
        if (!books.any((row) => row['id'] == bookId)) bookId = books.isEmpty ? null : books.first['id'] as int;
        from.text = book?['cutover_date']?.toString() ?? clubToday();
        message = books.isEmpty ? 'No accounting book exists yet. An authorised officer can set one up here.' : 'Amounts and ownership calculations come from the OpFin API.';
      });
      if (bookId != null && canMake) { final values = await request('catalogue'); if (mounted) setState(() => catalogue = values); }
    } catch (error) { if (mounted) setState(() => message = error is ClubApiException ? error.message : error is FormatException ? error.message.toString() : 'Could not load accounting. Try again.'); }
  }
  Future<void> chooseBook(int id) async {
    setState(() { bookId = id; result = {}; catalogue = {}; page = 1; from.text = book?['cutover_date']?.toString() ?? clubToday(); });
    if (canMake) { final values = await request('catalogue'); if (mounted) setState(() => catalogue = values); }
  }
  Future<void> loadView(String next, [int nextPage = 1]) async {
    final input = <String, dynamic>{};
    if (next == 'report') input.addAll({'period_start': clubIsoDate(from.text), 'period_end': clubIsoDate(through.text)});
    if (next == 'report' || next == 'statements') {
      if (ownOnly) { if (userId == null) throw const FormatException('Your current identity is unavailable.'); input['member_user_id'] = userId; }
    }
    if (['instructions', 'journals', 'statements'].contains(next)) input['page'] = nextPage;
    final values = await request(next, input: input);
    if (mounted) setState(() { result = values; view = next; page = nextPage; more = values['has_more'] == true; message = 'Loaded the authorised API records.'; });
  }
  Future<void> createBook() async {
    final fields = <Map<String, dynamic>>[
      {'key':'currency','label':'Currency, for example UGX','type':'string','required':true},
      {'key':'ownership_model','label':'Ownership model','type':'string','required':true,'enum':['capital_accounts','unitised']},
      {'key':'cutover_date','label':'Cutover date','type':'date','required':true},
      {'key':'initial_unit_price_minor','label':'Initial unit price: unitised books only','type':'integer','required':false,'minimum':1},
      {'key':'valuation_max_age_days','label':'Approved maximum valuation age, days','type':'integer','required':true,'minimum':1,'maximum':366},
    ];
    final values = await Navigator.push<Map<String, dynamic>>(context, MaterialPageRoute(builder: (_) => _ClubForm(
      title:'Set up club accounting', effect:'Choose the approved book policy. Opening balances and ownership require separate independent approval.',
      fields:fields, catalogue:const {}, initial:{'currency':widget.space['currency']??'UGX','ownership_model':'capital_accounts','cutover_date':clubToday(),'valuation_max_age_days':''})));
    if (values == null || !mounted) return;
    if (values['ownership_model'] == 'capital_accounts') values.remove('initial_unit_price_minor');
    await api.call('create-book', spaceId, input:values); await _load();
  }
  Future<void> newInstruction(Map<String, dynamic> operation) async {
    if (bookId == null || userId == null) return;
    await Navigator.push<void>(context, MaterialPageRoute(builder: (_) => ClubInstructionEditor(
      gateway:api, spaceId:spaceId, bookId:bookId!, userId:userId!, operation:operation, catalogue:catalogue,
      currency:book?['currency']?.toString()??'', canCheck:canCheck)));
    if (mounted) await _load();
  }
  Future<void> inspect(int id) async {
    final data = await request('instruction', target:id);
    if (!mounted || userId == null || bookId == null) return;
    await Navigator.push<void>(context, MaterialPageRoute(builder: (_) => ClubInstructionReview(gateway:api, spaceId:spaceId,
      bookId:bookId!, userId:userId!, canCheck:canCheck, instruction:(data['instruction'] as Map).cast<String,dynamic>())));
  }
  Future<void> issue() async {
    if (bookId == null || userId == null) return;
    final key = 'opfin:club-statement:$userId:$spaceId:$bookId';
    final saved = await storage.read(key:key);
    Map<String,dynamic> input;
    if (saved != null) {
      input = (jsonDecode(saved) as Map).cast<String,dynamic>();
    } else {
      input = {'period_start':clubIsoDate(from.text),'period_end':clubIsoDate(through.text),
        if(ownOnly)'member_user_id':userId,'idempotency_key':clubRequestKey()};
    }
    if (!mounted) return;
    final confirmed = await showDialog<bool>(context:context,builder:(context)=>AlertDialog(title:const Text('Issue a frozen statement'),
      content:Text('${input['period_start']} to ${input['period_end']}. ${input.containsKey('member_user_id')?'Your own member record.':'Club accounts.'} '
        '${saved != null?'This resumes the previous unchanged request. ':''}This does not execute a payment or certify bank reconciliation.'),
      actions:[TextButton(onPressed:()=>Navigator.pop(context,false),child:const Text('Cancel')),
        FilledButton(onPressed:()=>Navigator.pop(context,true),child:const Text('Issue statement'))]));
    if (confirmed != true) return;
    await storage.write(key:key,value:jsonEncode(input));
    final data = await request('issue-statement',input:input);
    await storage.delete(key:key);
    if (mounted) setState(() {result=data;view='statement';message='Frozen statement issued.';});
  }
  @override Widget build(BuildContext context) => Scaffold(appBar:AppBar(title:const Text('Club accounting'), actions:[IconButton(
    tooltip:'Refresh accounting',onPressed:busy?null:()=>run(_load),icon:const Icon(Icons.refresh))]),body:ListView(padding:const EdgeInsets.all(20),children:[
    Semantics(liveRegion:true,child:Text(message)),const SizedBox(height:12),
    const Text('Bookkeeping, ownership and provider payments are distinct. Approval here records club accounts; it does not send money.'),
    if(books.isNotEmpty)Padding(padding:const EdgeInsets.symmetric(vertical:16),child:DropdownButtonFormField<int>(
      key:ValueKey(bookId),initialValue:bookId,isExpanded:true,decoration:const InputDecoration(labelText:'Accounting book'),
      items:books.map((row)=>DropdownMenuItem(value:row['id'] as int,child:Text('${row['currency']} · ${clubLabel(row['ownership_model'].toString())} · ${row['status']}'))).toList(),
      onChanged:busy?null:(id){if(id!=null)run(()=>chooseBook(id));})),
    if(canMake)OutlinedButton.icon(onPressed:busy?null:()=>run(createBook),icon:const Icon(Icons.add),label:const Text('Set up another currency book')),
    if(bookId!=null)...[
      Text('Cutover: ${book?['cutover_date']}; ${book?['closed_through'] == null?'no closed period recorded':'closed through ${book?['closed_through']}'}'),
      if(book?['my_position']!=null)ClubDataView(value:book!['my_position'],label:'My capital'),
      const SizedBox(height:20),Text('Reports and statements',style:Theme.of(context).textTheme.titleLarge),
      TextField(controller:from,decoration:const InputDecoration(labelText:'From (YYYY-MM-DD)')),
      TextField(controller:through,decoration:const InputDecoration(labelText:'Through (YYYY-MM-DD)')),
      if(canMake)CheckboxListTile(contentPadding:EdgeInsets.zero,title:const Text('My member records only'),value:ownOnly,onChanged:busy?null:(v)=>setState(()=>ownOnly=v??true)),
      Wrap(spacing:8,children:[OutlinedButton(onPressed:busy?null:()=>run(()=>loadView('report')),child:const Text('View report')),
        OutlinedButton(onPressed:busy?null:()=>run(()=>loadView('statements')),child:const Text('Issued statements')),
        FilledButton(onPressed:busy?null:()=>run(issue),child:const Text('Issue statement'))]),
      if(canMake)...[
        const SizedBox(height:20),Text('Finance officer tasks',style:Theme.of(context).textTheme.titleLarge),
        Wrap(spacing:8,children:[OutlinedButton(onPressed:busy?null:()=>run(()=>loadView('instructions')),child:const Text('Instruction queue')),
          OutlinedButton(onPressed:busy?null:()=>run(()=>loadView('journals')),child:const Text('Journal history')),
          OutlinedButton(onPressed:busy?null:()=>run(()=>loadView('integrity')),child:const Text('Integrity checks'))]),
        ExpansionTile(title:const Text('Create a guided accounting instruction'),children:operations.map((operation)=>ListTile(
          title:Text(operation['title'].toString()),subtitle:Text(operation['effect'].toString()),trailing:const Icon(Icons.chevron_right),
          onTap:busy?null:()=>run(()=>newInstruction(operation)))).toList()),
      ],
      if(result.isNotEmpty)...[
        const Divider(),Text(clubLabel(view),style:Theme.of(context).textTheme.titleLarge),
        if(view=='instructions')...clubRows(result['instructions']).map((item)=>ListTile(title:Text(clubLabel(item['type'].toString())),
          subtitle:Text('${item['reference']} · ${item['status']}'),trailing:const Icon(Icons.chevron_right),onTap:busy?null:()=>run(()=>inspect(item['id'] as int))))
        else ClubDataView(value:result),
        if(view=='statements')...clubRows(result['statements']).map((item)=>OutlinedButton(onPressed:busy?null:()=>run(()async{
          final data=await request('statement',target:item['id'] as int);if(mounted)setState((){result=data;view='statement';});
        }),child:Text('Open ${item['reference']}'))),
        if(['instructions','journals','statements'].contains(view))Row(children:[TextButton(onPressed:busy||page<=1?null:()=>run(()=>loadView(view,page-1)),child:const Text('Previous')),
          Text('Page $page'),TextButton(onPressed:busy||!more?null:()=>run(()=>loadView(view,page+1)),child:const Text('Next'))]),
      ],
    ],
    if(busy)const Padding(padding:EdgeInsets.all(16),child:LinearProgressIndicator()),
  ]));
}

class _ClubForm extends StatefulWidget {
  const _ClubForm({required this.title, required this.effect, required this.fields, required this.catalogue, required this.initial});
  final String title,effect;
  final List<Map<String,dynamic>> fields;
  final Map<String,dynamic> catalogue,initial;
  @override State<_ClubForm> createState()=>_ClubFormState();
}
class _ClubFormState extends State<_ClubForm> {
  late Map<String,dynamic> values;
  String error='';
  @override void initState(){super.initState();values=clubCopy(widget.initial);}
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:Text(widget.title)),body:ListView(padding:const EdgeInsets.all(20),children:[
    Text(widget.effect),if(error.isNotEmpty)Semantics(liveRegion:true,child:Text(error)),
    ...widget.fields.map((field)=>ClubFieldEditor(field:field,value:values[field['key']],catalogue:widget.catalogue,
      onChanged:(value)=>setState(()=>values[field['key'] as String]=value))),
    FilledButton(onPressed:(){try{Navigator.pop(context,clubValidate(widget.fields,values));}on FormatException catch(e){setState(()=>error=e.message.toString());}},child:const Text('Continue')),
  ]));
}

class ClubInstructionEditor extends StatefulWidget {
  const ClubInstructionEditor({super.key,required this.gateway,required this.spaceId,required this.bookId,required this.userId,
    required this.operation,required this.catalogue,required this.currency,required this.canCheck});
  final ClubAccountingGateway gateway;
  final int spaceId,bookId,userId;
  final Map<String,dynamic> operation,catalogue;
  final String currency;
  final bool canCheck;
  @override State<ClubInstructionEditor> createState()=>_ClubInstructionEditorState();
}
class _ClubInstructionEditorState extends State<ClubInstructionEditor> {
  final storage=const FlutterSecureStorage();
  final date=TextEditingController(text:clubToday());
  late Map<String,dynamic> values;
  Map<String,dynamic>? frozen;
  bool busy=false,loaded=false;
  String message='',keyValue=clubRequestKey();
  String get storageKey=>'opfin:club-draft:${widget.userId}:${widget.spaceId}:${widget.bookId}';
  @override void initState(){super.initState();values=clubEmptyFields(clubRows(widget.operation['fields']));restore();}
  @override void dispose(){date.dispose();super.dispose();}
  Future<void> restore()async{
    try{final saved=await storage.read(key:storageKey);if(!mounted)return;
      if(saved!=null){final record=(jsonDecode(saved) as Map).cast<String,dynamic>();
        setState((){frozen=record;date.text=record['business_date'].toString();keyValue=record['idempotency_key'].toString();
          message='An earlier unchanged instruction is awaiting a known result. Resolve it before creating another.';});}
    }catch(_){if(mounted)setState(()=>message='Secure recovery storage is unavailable. No new instruction has been sent.');return;}
    if(mounted)setState(()=>loaded=true);
  }
  Future<void> submit()async{
    if(busy||!loaded)return;setState(()=>busy=true);
    try{
      final envelope=frozen??{'type':widget.operation['type'],'business_date':clubIsoDate(date.text),
        'idempotency_key':keyValue,'payload':clubValidate(clubRows(widget.operation['fields']),values)};
      await storage.write(key:storageKey,value:jsonEncode(envelope));
      if(mounted)setState(()=>frozen=envelope);
      final data=await widget.gateway.call('submit',widget.spaceId,bookId:widget.bookId,input:envelope);
      final item=(data['instruction'] as Map).cast<String,dynamic>();
      await storage.delete(key:storageKey);
      if(!mounted)return;
      await Navigator.pushReplacement<void,void>(context,MaterialPageRoute(builder:(_)=>ClubInstructionReview(gateway:widget.gateway,
        spaceId:widget.spaceId,bookId:widget.bookId,userId:widget.userId,canCheck:widget.canCheck,instruction:item)));
    }catch(error){
      if(error is ClubApiException && error.definitiveRejection){
        // Keep the same request key when correcting input; do not create a new
        // identity while a conflicting original request might already exist.
        if(mounted)setState(()=>frozen=null);
      }
      if(mounted)setState(()=>message=error is ClubApiException?error.message:error is FormatException?error.message.toString():'The result is uncertain. Resume this unchanged request using its saved key.');
    }
    finally{if(mounted)setState(()=>busy=false);}
  }
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:Text(widget.operation['title'].toString())),body:ListView(padding:const EdgeInsets.all(20),children:[
    Text(widget.operation['effect'].toString()),Text('Currency: ${widget.currency}. All financial calculations remain on the API.'),
    Semantics(liveRegion:true,child:Text(message)),TextField(controller:date,enabled:loaded&&!busy&&frozen==null,decoration:const InputDecoration(labelText:'Business date (YYYY-MM-DD)')),
    if(frozen!=null)ClubDataView(value:frozen,label:'Unchanged request') else
      ...clubRows(widget.operation['fields']).map((field)=>ClubFieldEditor(field:field,value:values[field['key']],catalogue:widget.catalogue,
        enabled:loaded&&!busy,onChanged:(value)=>setState(()=>values[field['key'] as String]=value))),
    SelectableText('Request key: $keyValue'),const SizedBox(height:12),
    FilledButton(onPressed:busy||!loaded?null:submit,child:Text(frozen==null?'Submit for independent approval':'Resume unchanged request')),
    const Text('Submission creates a pending accounting instruction. It does not approve itself or execute a bank payment.'),
  ]));
}

class ClubInstructionReview extends StatefulWidget {
  const ClubInstructionReview({super.key,required this.gateway,required this.spaceId,required this.bookId,required this.userId,
    required this.canCheck,required this.instruction});
  final ClubAccountingGateway gateway;
  final int spaceId,bookId,userId;
  final bool canCheck;
  final Map<String,dynamic> instruction;
  @override State<ClubInstructionReview> createState()=>_ClubInstructionReviewState();
}
class _ClubInstructionReviewState extends State<ClubInstructionReview> {
  late Map<String,dynamic> item;
  Map<String,dynamic>? preview;
  final reason=TextEditingController();
  bool reviewed=false,busy=false;
  String message='Check the source evidence and exact instruction before approval.';
  @override void initState(){super.initState();item=clubCopy(widget.instruction);}
  @override void dispose(){reason.dispose();super.dispose();}
  Future<void> decide(String action)async{
    if(busy)return;setState(()=>busy=true);
    try{final input=<String,dynamic>{if(action=='approve')'payload_hash':item['payload_hash'],if(action=='reject'||action=='cancel')'reason':reason.text.trim()};
      final data=await widget.gateway.call(action,widget.spaceId,bookId:widget.bookId,targetId:item['id'] as int,input:input);
      if(!mounted)return;
      setState((){if(action=='preview'){preview=data;message='Simulation only. Approval uses current authorised records, not these simulated identifiers.';}
        else{item=(data['instruction'] as Map).cast<String,dynamic>();preview=null;reviewed=false;message='Decision recorded by the API.';}});
    }catch(error){if(mounted)setState(()=>message=error is ClubApiException?error.message:error is FormatException?error.message.toString():'Check the instruction status before retrying this decision.');}
    finally{if(mounted)setState(()=>busy=false);}
  }
  @override Widget build(BuildContext context){final maker=item['maker_id']==widget.userId;return Scaffold(appBar:AppBar(title:const Text('Review club instruction')),
    body:ListView(padding:const EdgeInsets.all(20),children:[Semantics(liveRegion:true,child:Text(message)),
      Text('${item['reference']} · ${item['status']}'),Text('Business date: ${item['business_date']} · Maker: ${item['maker_id']}'),
      ClubDataView(value:item['payload']),SelectableText('Exact approval hash: ${item['payload_hash']}'),
      if(item['result']!=null)ClubDataView(value:item['result'],label:'Recorded outcome'),
      if(item['status']=='pending')...[
        OutlinedButton(onPressed:busy?null:()=>decide('preview'),child:const Text('Simulate current outcome')),
        if(preview!=null)ClubDataView(value:preview,label:'Simulation'),
        if(widget.canCheck&&!maker)...[CheckboxListTile(contentPadding:EdgeInsets.zero,value:reviewed,onChanged:busy?null:(value)=>setState(()=>reviewed=value??false),
          title:const Text('I checked the source, currency, members, amounts and accounting effect.')),
          FilledButton(onPressed:busy||!reviewed?null:()=>decide('approve'),child:const Text('Approve this exact instruction'))],
        TextField(controller:reason,maxLength:500,onChanged:(_)=>setState((){}),decoration:const InputDecoration(labelText:'Reason for rejection or cancellation')),
        if(maker)OutlinedButton(onPressed:busy||reason.text.trim().isEmpty?null:()=>decide('cancel'),child:const Text('Cancel my pending instruction'))
        else if(widget.canCheck)OutlinedButton(onPressed:busy||reason.text.trim().isEmpty?null:()=>decide('reject'),child:const Text('Reject with reason')),
      ],
    ]));}
}
