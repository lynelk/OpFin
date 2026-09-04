import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/loan_application_screen.dart';
import 'package:opfin/models/product_term.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/services/user_session.dart';

class ProductTermsPage extends StatefulWidget {
  final int productId;
  final String productName;

  const ProductTermsPage(
      {super.key, required this.productId, required this.productName});

  @override
  ProductTermsPageState createState() => ProductTermsPageState();
}

class ProductTermsPageState extends State<ProductTermsPage> {
  late Future<List<ProductTerm>> futureProductTerms;

  @override
  void initState() {
    super.initState();
    futureProductTerms = fetchProductTerms(widget.productId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.black),
        title: Text(
          "${widget.productName} Terms",
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            color: Colors.black,
            fontSize: 22,
          ),
        ),
        foregroundColor: Colors.black,
      ),
      body: FutureBuilder<List<ProductTerm>>(
        future: futureProductTerms,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(
              child: CircularProgressIndicator(color: Colors.black),
            );
          }

          if (snapshot.hasError) {
            return Center(
              child: Text(
                "Error: ${snapshot.error}",
                style: const TextStyle(color: Colors.black),
              ),
            );
          }

          if (!snapshot.hasData || snapshot.data!.isEmpty) {
            return const Center(
              child: Text(
                "No terms available for this product",
                style: TextStyle(color: Colors.black),
              ),
            );
          }

          final terms = snapshot.data!;

          return Padding(
            padding: const EdgeInsets.all(16.0),
            child: ListView.separated(
              itemCount: terms.length,
              separatorBuilder: (_, __) => const SizedBox(height: 16),
              itemBuilder: (context, index) {
                final term = terms[index];

                return GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => LoanApplicationScreen(
                          loanProductId: widget.productId,
                          loanProductTermId: term.id,
                          institutionId: term.product.institution?.id,
                        ),
                      ),
                    );
                  },
                  child: Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: Colors.black12),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: .05),
                          blurRadius: 6,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Top Row
                        Row(
                          children: [
                            Container(
                              height: 48,
                              width: 48,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border:
                                    Border.all(color: Colors.black, width: 2),
                              ),
                              child: const Icon(Icons.info_outline,
                                  color: Colors.black),
                            ),
                            const SizedBox(width: 16),
                            Expanded(
                              child: Text(
                                "${term.interestRate}% Interest",
                                style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w800,
                                  color: Colors.black,
                                ),
                              ),
                            ),
                          ],
                        ),

                        const SizedBox(height: 20),

                        _infoRow("Interest Type", term.interestType),
                        // _infoRow("Interest Cycle", term.interestCycle),
                        // _infoRow(
                        //     "Repayment Frequency", term.repaymentFrequency),
                        _infoRow("Duration", "${term.duration} days"),
                        _infoRow("Status", term.status),
                        _infoRow("Institution",
                            term.product.institution?.name ?? "Unknown"),

                        const SizedBox(height: 20),

                        // Apply Button
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => LoanApplicationScreen(
                                    loanProductId: widget.productId,
                                    loanProductTermId: term.id,
                                    institutionId: term.product.institution?.id,
                                  ),
                                ),
                              );
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.black,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text(
                              "Apply",
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }

// Helper Row Widget
  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 14, color: Colors.black54),
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: Colors.black,
            ),
          ),
        ],
      ),
    );
  }

  Future<List<ProductTerm>> fetchProductTerms(int productId) async {
    final token = await UserSession.getAccessToken();

    final response = await http.get(
      Uri.parse("$apiUrl/product-terms/$productId"),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      List<ProductTerm> productTerms = (data['data'] as List)
          .map((item) => ProductTerm.fromJson(item))
          .toList();
      return productTerms;
    } else {
      throw Exception("Failed to load product terms");
    }
  }
}
