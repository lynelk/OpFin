import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/club_accounting/contracts.dart';
import 'package:opfin/services/club_accounting_api.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('integer input preserves exact minor units', () {
    expect(clubInteger('1000000'), 1000000);
    for (final value in ['1.5', '1,000', '-1', '1e3', true, null, '9007199254740992']) {
      expect(() => clubInteger(value), throwsFormatException);
    }
  });
  test('calendar validation rejects normalised invalid dates', () {
    expect(clubIsoDate('2024-02-29'), '2024-02-29');
    for (final value in ['2026-02-29', '2026-02-30', '2026-13-01']) {
      expect(() => clubIsoDate(value), throwsFormatException);
    }
  });
  test('only schema fields can be submitted', () {
    final fields = <Map<String,dynamic>>[{'key':'amount_minor','label':'Amount','type':'integer','required':true,'minimum':1}];
    expect(clubValidate(fields, {'amount_minor':'12'}), {'amount_minor':12});
    expect(() => clubValidate(fields, {'amount_minor':12,'status':'approved'}), throwsFormatException);
  });
  test('opening allocation arrays can genuinely be empty', () {
    final fields = <Map<String,dynamic>>[{'key':'members','label':'Members','type':'array','required':true,'items':<dynamic>[]}];
    expect(clubValidate(fields, {'members':<dynamic>[]}), {'members':<dynamic>[]});
  });
  test('unknown future form types fail rather than guessing financial meaning', () {
    expect(() => clubValidate([{'key':'x','label':'X','type':'execute','required':true}], {'x':'run'}), throwsFormatException);
  });
  test('request keys are bounded and distinct', () {
    final keys = List.generate(100, (_) => clubRequestKey());
    expect(keys.toSet().length, 100);
    expect(keys.every((key) => RegExp(r'^club-[a-f0-9]{32}$').hasMatch(key)), isTrue);
  });
  test('gateway rejects arbitrary operations without sending them', () async {
    var calls = 0;
    final api = ClubAccountingApi(client:MockClient((request) async {calls++;return http.Response('{}',500);}), token:() async=>'synthetic-test-token');
    await expectLater(api.call('execute-payment',1), throwsFormatException);
    expect(calls,0);api.close();
  });
  test('gateway uses the exact authorised book route and keeps tokens out of its URL', () async {
    final api = ClubAccountingApi(client:MockClient((request) async {
      expect(request.url.path.endsWith('/financial-spaces/2/accounting/books/3/instructions'),isTrue);
      expect(request.url.toString().contains('synthetic-test-token'),isFalse);
      expect(request.headers['Authorization'],'Bearer synthetic-test-token');
      return http.Response('{"success":true,"data":{"instructions":[]}}',200,headers:{'content-type':'application/json'});
    }),token:() async=>'synthetic-test-token');
    expect(await api.call('instructions',2,bookId:3),{'instructions':<dynamic>[]});api.close();
  });
  test('validation rejection is different from an uncertain transport failure', () async {
    final api = ClubAccountingApi(client:MockClient((request) async=>http.Response('{"success":false,"message":"Invalid date"}',422)),token:() async=>'synthetic');
    await expectLater(api.call('submit',2,bookId:3),throwsA(isA<ClubApiException>().having((e)=>e.definitiveRejection,'definitive rejection',true)));
    api.close();
  });
}
