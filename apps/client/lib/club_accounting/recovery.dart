import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:opfin/services/club_accounting_api.dart';
import 'contracts.dart';
import 'fields.dart';

class ClubRecoveryScreen extends StatefulWidget {
  const ClubRecoveryScreen({super.key, this.spaceId, this.gateway});
  final int? spaceId;
  final ClubAccountingGateway? gateway;
  @override State<ClubRecoveryScreen> createState() => _ClubRecoveryScreenState();
}

class _ClubRecoveryScreenState extends State<ClubRecoveryScreen> {
  late final ClubAccountingGateway api;
  List<Map<String,dynamic>> records = [];
  Map<String,dynamic>? selected, result;
  var message = 'Saved requests survive a reload, device restart or lost response.';
  var busy = false, more = false;
  var page = 1;
  @override void initState() { super.initState(); api = widget.gateway ?? ClubAccountingApi(); load(); }
  @override void dispose() {
    if (widget.gateway == null && api is ClubAccountingApi) (api as ClubAccountingApi).close();
    super.dispose();
  }
  Future<void> load([int nextPage = 1]) async {
    try {
      final response = await api.call('saved-requests', widget.spaceId ?? 1,
        input:{'page':nextPage, if(widget.spaceId != null)'space_id':widget.spaceId});
      if (mounted) setState(() { records = clubRows(response['requests']); page = nextPage; more = response['has_more'] == true; });
    } catch (error) { if (mounted) setState(() => message = error.toString()); }
  }
  Future<void> inspect(Map<String,dynamic> record) async {
    if (busy) return;
    setState(() => busy = true);
    try {
      final response = await api.call('inspect-saved', clubInteger(record['financial_space_id'],1),
        bookId:clubInteger(record['book_id'],1), input:{'reference':record['reference'], 'content_hash':record['content_hash']});
      if (mounted) setState(() {selected={...record,...response};result=null;});
    } catch (error) {if(mounted)setState(()=>message=error.toString());}
    finally {if(mounted)setState(()=>busy=false);}
  }
  Future<void> action(String action) async {
    if (busy || selected == null) return;
    setState(() => busy = true);
    try {
      final response = await api.call(action, clubInteger(selected!['financial_space_id'],1),
        bookId:clubInteger(selected!['book_id'],1), input:{'reference':selected!['reference'], 'content_hash':selected!['content_hash']});
      if (!mounted) return;
      if (action == 'resume-saved') {
        setState(() {result = response; message = 'Read this original result. Acknowledging it does not approve an instruction or send money.';});
      } else {
        // Remove only this exact user's matching local fallback, never another
        // draft or a newer instruction created on the same device.
        final original = selected!;
        final profile = await api.call('profile', 1);
        final actor = clubInteger((profile['user'] as Map)['id'], 1);
        final suffix = original['purpose'] == 'instruction' ? 'draft' : 'statement';
        final localKey = 'opfin:club-$suffix:$actor:${original['financial_space_id']}:${original['book_id']}';
        const storage = FlutterSecureStorage();
        final local = await storage.read(key: localKey);
        if (local != null) {
          final decoded = jsonDecode(local);
          final envelope = original['envelope'];
          if (decoded is Map && envelope is Map && decoded['idempotency_key'] == envelope['idempotency_key']) {
            await storage.delete(key: localKey);
          }
        }
        if (!mounted) return;
        setState(() {selected = null; result = null; message = action == 'cancel-saved'
            ? 'Unsubmitted request cancelled. Reopen the form to create a different request.' : 'Recorded result acknowledged.';});
        await load(page);
      }
    } catch (error) { if (mounted) setState(() => message = error.toString()); }
    finally { if (mounted) setState(() => busy = false); }
  }
  @override Widget build(BuildContext context) => Scaffold(appBar:AppBar(title:const Text('Saved accounting requests')),
    body:ListView(padding:const EdgeInsets.all(20),children:[
      Semantics(liveRegion:true,child:Text(message)),
      OutlinedButton(onPressed:busy?null:()=>load(page),child:const Text('Refresh saved requests')),
      if(records.isEmpty)const Text('No saved requests on this page.'),
      ...records.map((record)=>ListTile(title:Text('${record['currency']} · ${record['purpose']} · ${record['status']}'),
        subtitle:Text('Book ${record['book_id']}'),onTap:busy?null:()=>inspect(record))),
      Row(children:[TextButton(onPressed:busy||page<=1?null:()=>load(page-1),child:const Text('Previous')),
        Text('Page $page'),TextButton(onPressed:busy||!more?null:()=>load(page+1),child:const Text('Next'))]),
      if(selected!=null)...[
        ClubDataView(value:selected!['envelope'],label:'Original saved request'),
        FilledButton(onPressed:busy?null:()=>action('resume-saved'),child:const Text('Resume or read original result')),
        if(selected!['status']=='prepared'&&result==null)OutlinedButton(onPressed:busy?null:()=>action('cancel-saved'),child:const Text('Cancel this unsubmitted request')),
        if(result!=null)...[ClubDataView(value:result,label:'Recorded result'),
          FilledButton(onPressed:busy?null:()=>action('acknowledge-saved'),child:const Text('I have read this result'))],
      ],
      if(busy)const LinearProgressIndicator(),
    ]));
}
