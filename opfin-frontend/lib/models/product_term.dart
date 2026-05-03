import 'package:opfin/models/product.dart';

class ProductTerm {
  final int id;
  final int loanProductId;
  final String interestRate;
  final String interestType;
  final String interestCycle;
  final String repaymentFrequency;
  final int duration;
  final String status;
  final Product product;

  ProductTerm({
    required this.id,
    required this.loanProductId,
    required this.interestRate,
    required this.interestType,
    required this.interestCycle,
    required this.repaymentFrequency,
    required this.duration,
    required this.status,
    required this.product,
  });

  factory ProductTerm.fromJson(Map<String, dynamic> json) {
    return ProductTerm(
      id: json['id'],
      loanProductId: json['loan_product_id'],
      interestRate: json['interest_rate'],
      interestType: json['interest_type'],
      interestCycle: json['interest_cycle'],
      repaymentFrequency: json['repayment_frequency'],
      duration: json['duration'],
      status: json['status'],
      product: Product.fromJson(json['product']),
    );
  }
}
