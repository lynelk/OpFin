import 'package:flutter/material.dart';
import 'package:opfin/services/club_accounting_api.dart';
import 'contracts.dart';
import 'fields.dart';
import 'recovery.dart';
import 'statement_export.dart';

/// No current membership is required to reach a member's retained own records.
class ClubHistoryScreen extends StatefulWidget {
  const ClubHistoryScreen({super.key, this.spaceId, this.gateway});
  final int? spaceId;
  final ClubAccountingGateway? gateway;
  @override State<ClubHistoryScreen> createState() => _ClubHistoryScreenState();
}

class _ClubHistoryScreenState extends State<ClubHistoryScreen> {
  late final ClubAccountingGateway api;
  final exporter = ClubStatementExport();
  final from = TextEditingController(), through = TextEditingController(text:clubToday());
  List<Map<String,dynamic>> books = [], statements = [];
  Map<String,dynamic>? selected, report;
  int? userId;
  bool officer = false, ownOnly = true, busy = false, more = false;
  int page = 1;
  String message = 'Loading your own club financial history…';
  @override void initState() {super.initState(); api=widget.gateway??ClubAccountingApi();load();}
  @override void dispose() {
    from.dispose();through.dispose();exporter.close();
    if(widget.gateway==null&&api is ClubAccountingApi)(api as ClubAccountingApi).close();
    super.dispose();
  }
  Future<void> load() async {
    try {
      final profile=await api.call('profile',1);
      final response=await api.call(widget.spaceId==null?'my-books':'books',widget.spaceId??1);
      if(!mounted)return;
      setState(() {
        userId=clubInteger((profile['user'] as Map)['id'],1);
        books=clubRows(response['books']).map((row)=>{...row,
          if(widget.spaceId!=null)'financial_space_id':widget.spaceId}).toList();
        officer=widget.spaceId!=null&&response['can_make']==true;
        message='Your member history remains available after leaving a club. Currencies stay separate.';
      });
    }catch(error){if(mounted)setState(()=>message=error.toString());}
  }
  Future<void> run(Future<void> Function() operation) async {
    if(busy)return;setState(()=>busy=true);
    try{await operation();}catch(error){if(mounted)setState(()=>message=error.toString());}
    finally{if(mounted)setState(()=>busy=false);}
  }
  Future<void> records(String kind,[int nextPage=1]) async {
    if(selected==null||userId==null)return;
    final input=<String,dynamic>{
      if(kind=='statements')'page':nextPage,
      if(kind!='statements')'period_start':clubIsoDate(from.text),
      if(kind!='statements')'period_end':clubIsoDate(through.text),
      if(ownOnly)'member_user_id':userId,
      if(kind=='issue-statement')'idempotency_key':clubRequestKey(),
    };
    final response=await api.call(kind,clubInteger(selected!['financial_space_id'],1),
      bookId:clubInteger(selected!['id'],1),input:input);
    if(!mounted)return;
    setState((){
      if(kind=='statements'){statements=clubRows(response['statements']);page=nextPage;more=response['has_more']==true;}
      else report=response;
      message=kind=='issue-statement'?'Statement recorded. Read and acknowledge its saved request before issuing another.':'Loaded authorised financial records.';
    });
  }
  Future<void> exportStatement(Map<String,dynamic> statement,String format,String mode) async {
    if(selected==null)return;
    final status=await exporter.open(spaceId:clubInteger(selected!['financial_space_id'],1),
      bookId:clubInteger(selected!['id'],1),statementId:clubInteger(statement['id'],1),format:format,mode:mode);
    if(mounted)setState(()=>message={
      'saved':'The statement was saved to your chosen destination.',
      'completed':'The device reported the export complete.',
      'cancelled':'Export cancelled. No financial record was changed.',
      'presented':'The device share or print dialogue is open. Complete the operation there.',
    }[status]??'Check the export destination.');
  }
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:const Text('Club records and exports')),
    body:ListView(padding:const EdgeInsets.all(20),children:[
      Semantics(liveRegion:true,child:Text(message)),
      OutlinedButton(onPressed:()=>Navigator.push(context,MaterialPageRoute(builder:(_)=>ClubRecoveryScreen(spaceId:widget.spaceId,gateway:widget.gateway))),child:const Text('Saved requests and recovery')),
      if(books.isEmpty)const Text('No retained club accounting position was found.'),
      ...books.map((book)=>Card(child:ListTile(title:Text('${book['name']??'Accounting book'} · ${book['currency']}'),
        subtitle:Text(clubLabel(book['ownership_model'].toString())),onTap:busy?null:()=>setState(() {
          selected=book;from.text=book['cutover_date'].toString();statements=[];report=null;page=1;ownOnly=true;
        })))),
      if(selected!=null)...[
        TextField(controller:from,decoration:const InputDecoration(labelText:'From (YYYY-MM-DD)')),
        TextField(controller:through,decoration:const InputDecoration(labelText:'Through (YYYY-MM-DD)')),
        if(officer)CheckboxListTile(title:const Text('My member records only'),value:ownOnly,
          onChanged:busy?null:(value)=>setState((){ownOnly=value??true;statements=[];report=null;})),
        Wrap(spacing:8,children:[
          OutlinedButton(onPressed:busy?null:()=>run(()=>records('report')),child:const Text('Read report')),
          OutlinedButton(onPressed:busy?null:()=>run(()=>records('statements')),child:const Text('Issued statements')),
          FilledButton(onPressed:busy?null:()=>run(()=>records('issue-statement')),child:const Text('Issue frozen statement')),
        ]),
        if(report!=null)ClubDataView(value:report,label:'Authorised record'),
        ...statements.map((statement)=>Card(child:Padding(padding:const EdgeInsets.all(12),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
          Text('${statement['period_start']} to ${statement['period_end']}'),SelectableText(statement['reference'].toString()),
          const Text('The file contains financial information. Choose its destination or recipient carefully.'),
          Wrap(spacing:8,children:[
            OutlinedButton(onPressed:busy?null:()=>run(()=>exportStatement(statement,'csv','save')),child:const Text('Save CSV')),
            OutlinedButton(onPressed:busy?null:()=>run(()=>exportStatement(statement,'csv','share')),child:const Text('Share CSV')),
            OutlinedButton(onPressed:busy?null:()=>run(()=>exportStatement(statement,'html','save')),child:const Text('Save statement')),
            OutlinedButton(onPressed:busy?null:()=>run(()=>exportStatement(statement,'html','print')),child:const Text('Print / save PDF')),
          ]),
        ])))),
        Row(children:[TextButton(onPressed:busy||page<=1?null:()=>run(()=>records('statements',page-1)),child:const Text('Previous')),
          Text('Page $page'),TextButton(onPressed:busy||!more?null:()=>run(()=>records('statements',page+1)),child:const Text('Next'))]),
      ],
      if(busy)const LinearProgressIndicator(),
    ]));
}
