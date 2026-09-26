import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/club_accounting/fields.dart';
import 'package:opfin/club_accounting/workspace.dart';
import 'package:opfin/services/club_accounting_api.dart';

class FakeClubGateway implements ClubAccountingGateway {
  final calls = <Map<String,dynamic>>[];
  @override Future<Map<String,dynamic>> call(String action,int spaceId,{int? bookId,int? targetId,Map<String,dynamic> input=const {}}) async {
    calls.add({'action':action,'space':spaceId,'book':bookId,'target':targetId,'input':input});
    return {'instruction':{'id':9,'reference':'SYNTHETIC','type':'contribution','business_date':'2026-09-26',
      'maker_id':1,'status':'approved','payload_hash':List.filled(64,'a').join(),'payload':{'amount_minor':100}}};
  }
}
void main(){
  testWidgets('a maker cannot approve their own instruction',(tester)async{
    final api=FakeClubGateway();
    await tester.pumpWidget(MaterialApp(home:ClubInstructionReview(gateway:api,spaceId:2,bookId:3,userId:1,canCheck:true,
      instruction:{'id':9,'reference':'SYNTHETIC','maker_id':1,'status':'pending','business_date':'2026-09-26',
        'payload_hash':List.filled(64,'a').join(),'payload':{'amount_minor':100}})));
    expect(find.text('Approve this exact instruction'),findsNothing);
    expect(api.calls,isEmpty);
  });
  testWidgets('removing an array row preserves displayed and submitted values',(tester)async{
    var values=<dynamic>[{'amount_minor':'100'},{'amount_minor':'200'}];
    await tester.pumpWidget(MaterialApp(home:Scaffold(body:StatefulBuilder(builder:(context,setState)=>SingleChildScrollView(child:ClubFieldEditor(
      field:{'key':'entries','label':'Entries','type':'array','required':true,'items':[
        {'key':'amount_minor','label':'Amount','type':'integer','required':true}]},value:values,catalogue:const {},
      onChanged:(next)=>setState(()=>values=List<dynamic>.from(next as List))))))));
    await tester.tap(find.text('Remove row 1'));await tester.pump();
    expect(values,[{'amount_minor':'200'}]);
    final field=tester.widget<TextFormField>(find.byType(TextFormField).first);
    expect(field.controller?.text,'200');
  });
}
