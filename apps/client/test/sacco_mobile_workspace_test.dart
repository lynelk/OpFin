import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/services/financial_spaces_api.dart';

void main(){
 test('mobile API exposes SACCO and Financial Space operations contracts',(){
  expect(FinancialSpacesApi.saccoPosition,isNotNull);
  expect(FinancialSpacesApi.saccoProducts,isNotNull);
  expect(FinancialSpacesApi.createOperation,isNotNull);
  expect(FinancialSpacesApi.approveOperation,isNotNull);
  expect(FinancialSpacesApi.operationsAnalytics,isNotNull);
  expect(FinancialSpacesApi.proposeGuarantee,isNotNull);
 });
}
