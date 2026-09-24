import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class FinancialSpacesApi {
  static Future<Map<String,String>> _headers() async {
    final token=await UserSession.getAccessToken();
    if(token==null||token.isEmpty) { throw Exception('Secure session is required.'); }
    return {'Accept':'application/json','Content-Type':'application/json','Authorization':'Bearer $token'};
  }
  static Future<dynamic> _request(String path,{String method='GET',Map<String,dynamic>? body}) async {
    final h=await _headers(); final uri=Uri.parse('$apiUrl$path');
    late http.Response r;
    if(method=='POST') { r=await http.post(uri,headers:h,body:jsonEncode(body??{})); }
    else if(method=='PUT') { r=await http.put(uri,headers:h,body:jsonEncode(body??{})); }
    else { r=await http.get(uri,headers:h); }
    final decoded=jsonDecode(r.body) as Map<String,dynamic>;
    if(r.statusCode<200||r.statusCode>=300) { throw Exception(decoded['message']?.toString()??'Unable to complete request.'); }
    return decoded['data'];
  }
  static Future<List<Map<String,dynamic>>> spaces() async => (((await _request('/financial-spaces'))['spaces'] as List?)??[]).map((e)=>(e as Map).cast<String,dynamic>()).toList();
  static Future<Map<String,dynamic>> createSpace(String type,String name) async => ((await _request('/financial-spaces',method:'POST',body:{'type':type,'name':name}))['space'] as Map).cast<String,dynamic>();
  static Future<Map<String,dynamic>> financialLife(int id) async => (await _request('/financial-spaces/$id/financial-life') as Map).cast<String,dynamic>();
  static Future<List<dynamic>> members(int id) async => ((await _request('/financial-spaces/$id/members'))['members'] as List?)??[];
  static Future<void> invite(int id,String phone,String role) async { await _request('/financial-spaces/$id/invitations',method:'POST',body:{'phone':phone,'role':role}); }
  static Future<List<Map<String,dynamic>>> credentials(int id) async => (((await _request('/financial-spaces/$id/credentials'))['credentials'] as List?)??[]).whereType<Map>().map((e)=>e.cast<String,dynamic>()).toList();
  static Future<Map<String,dynamic>> declareCredential(int id,{required String type,required String issuerCode,required String issuerName,required String value,String? country}) async => ((await _request('/financial-spaces/$id/credentials',method:'POST',body:{'credential_type':type,'issuer_code':issuerCode,'issuer_name':issuerName,'credential_value':value,if(country!=null)'jurisdiction_country':country}))['credential'] as Map).cast<String,dynamic>();
  static Future<List<Map<String,dynamic>>> groupProtectionProducts(int id) async => (((await _request('/financial-spaces/$id/protection/products'))['products'] as List?)??[]).whereType<Map>().map((e)=>e.cast<String,dynamic>()).toList();
  static Future<void> addAsset(int id,String type,String name,int value) async { await _request('/financial-spaces/$id/assets',method:'POST',body:{'asset_type':type,'name':name,'value_minor':value}); }
  static Future<List<Map<String,dynamic>>> obligations(int id) async => (((await _request('/financial-spaces/$id/obligations'))['obligations'] as List?)??[]).whereType<Map>().map((e)=>e.cast<String,dynamic>()).toList();
  static Future<void> addObligation(int id,String kind,String direction,String counterparty,int amount,{String? dueDate,String? currency}) async { await _request('/financial-spaces/$id/obligations',method:'POST',body:{'kind':kind,'direction':direction,'counterparty_name':counterparty,'amount_minor':amount,if(dueDate!=null)'due_date':dueDate,if(currency!=null)'currency':currency}); }
  static Future<void> settleObligation(int id,int obligationId,int amount) async { await _request('/financial-spaces/$id/obligations/$obligationId/settlements',method:'POST',body:{'amount_minor':amount}); }
}
