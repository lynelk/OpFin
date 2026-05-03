import 'package:opfin/models/institution.dart';

class Product {
  final int id;
  final String name;
  final String type;
  final String status;
  final Institution? institution;

  Product({
    required this.id,
    required this.name,
    required this.type,
    required this.status,
    this.institution,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'],
      name: json['name'],
      type: json['type'],
      status: json['status'],
      institution: json['institution'] != null
          ? Institution.fromJson(json['institution'])
          : null,
    );
  }
}
