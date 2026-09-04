import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/loan_repayment_screen.dart';
import 'package:opfin/services/user_session.dart';
import 'package:intl/intl.dart';

class LoanApplicationsScreen extends StatefulWidget {
  const LoanApplicationsScreen({super.key});

  @override
  LoanApplicationsScreenState createState() => LoanApplicationsScreenState();
}

class LoanApplicationsScreenState extends State<LoanApplicationsScreen> {
  Future<List<dynamic>> fetchLoanApplications() async {
    final userId = await UserSession.getUserId();
    final token = await UserSession.getAccessToken();
    final response = await http.get(
      Uri.parse('$apiUrl/loan-applications/$userId'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );

    if (response.statusCode == 200) {
      final jsonData = jsonDecode(response.body);
      return jsonData['data'];
    } else {
      throw Exception('Failed to load loan applications');
    }
  }

  String _formatNumber(dynamic number) {
    if (number == null) return '';
    return number.toString().replaceAllMapped(
        RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]},');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.black),
        title: const Text(
          'Loan Applications',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: Colors.black,
            fontSize: 22,
          ),
        ),
        centerTitle: false,
        foregroundColor: Colors.black,
      ),
      body: FutureBuilder<List<dynamic>>(
        future: fetchLoanApplications(),
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
                "No loan applications found.",
                style: TextStyle(color: Colors.black),
              ),
            );
          }

          final applications = snapshot.data!;

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: applications.length,
            separatorBuilder: (_, __) => const SizedBox(height: 16),
            itemBuilder: (context, index) {
              final application = applications[index];
              final status = application['status'];
              final loan = application['loan'];
              final hasLoan = loan != null && loan['status'] == 'Disbursed';

              final maturityDate =
                  (hasLoan && loan['repayment_start_date'] != null)
                      ? DateFormat('MMM dd, yyyy')
                          .format(DateTime.parse(loan['repayment_start_date']))
                      : "";

              return Container(
                padding: const EdgeInsets.all(20),
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
                    // Top Section
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          "UGX ${_formatNumber(application['amount'])}",
                          style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                            color: Colors.black,
                          ),
                        ),
                        Text(status),
                      ],
                    ),

                    const SizedBox(height: 18),
                    Divider(color: Colors.grey[300]),

                    const SizedBox(height: 12),

                    // Loan Info Rows
                    _infoRow(Icons.account_balance, "Institution",
                        application['institution']['name']),
                    _infoRow(Icons.access_time, "Loan Term",
                        "${application['loan_product_term']['duration']} days"),
                    _infoRow(Icons.percent, "Interest Rate",
                        "${application['loan_product_term']['interest_rate']}%"),

                    // Disbursed Section
                    if (hasLoan) ...[
                      const SizedBox(height: 20),
                      Divider(color: Colors.grey[300]),
                      const SizedBox(height: 12),
                      _infoRow(
                        Icons.attach_money,
                        "Amount Due",
                        "UGX ${_formatNumber(loan['outstanding_balance'])}",
                      ),
                      _infoRow(
                        Icons.event,
                        "Maturity Date",
                        maturityDate,
                      ),
                      const SizedBox(height: 20),
                      Align(
                        alignment: Alignment.centerRight,
                        child: ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.black,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(
                                vertical: 12, horizontal: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          icon: const Icon(Icons.payment, color: Colors.white),
                          label: const Text(
                            'Repay',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              color: Colors.white,
                            ),
                          ),
                          onPressed: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (context) => LoanRepaymentScreen(
                                  loanId: loan['id'],
                                  repaymentAmount: loan['outstanding_balance'],
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ]
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }

// ------------------------------------------------------------
// INFORMATION ROW
// ------------------------------------------------------------
  Widget _infoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(icon, color: Colors.black, size: 20),
          const SizedBox(width: 10),
          Text(
            "$label:",
            style: const TextStyle(
              fontWeight: FontWeight.w600,
              color: Colors.black,
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: const TextStyle(
                // fontWeight: FontWeight.w700,
                color: Colors.black,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
