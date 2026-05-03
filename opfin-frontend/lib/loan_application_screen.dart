import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:opfin/constants.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/loan_application_result_screen.dart';
import 'package:opfin/loan_confirmation_screen.dart';
import 'package:opfin/models/loan_application.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LoanApplicationScreen extends StatefulWidget {
  final int loanProductId;
  final int loanProductTermId;
  final int? institutionId;

  const LoanApplicationScreen({
    super.key,
    required this.loanProductId,
    required this.loanProductTermId,
    this.institutionId,
  });

  @override
  LoanApplicationScreenState createState() => LoanApplicationScreenState();
}

class LoanApplicationScreenState extends State<LoanApplicationScreen> {
  final _formKey = GlobalKey<FormState>();

  double amount = 0;
  bool _submitting = false;
  String? selectedReason; // to store the selected value
  final List<String> loanReasons = [
    'Medical Expenses',
    'Education',
    'Business Startup/Expansion',
    'Debt Consolidation',
    'Home Renovation/Repairs',
    'Purchase of Vehicle',
    'Wedding or Events',
    'Travel',
    'Personal Expenses',
    'Other',
  ];

  @override
  void initState() {
    super.initState();
    print(widget.institutionId);
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Scaffold(
          backgroundColor: Colors.white,
          appBar: AppBar(
            backgroundColor: Colors.white,
            elevation: 0,
            iconTheme: const IconThemeData(color: Colors.black),
            title: const Text(
              "Apply for a Loan",
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: Colors.black,
                fontSize: 22,
              ),
            ),
            foregroundColor: Colors.black,
          ),
          body: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
            child: Form(
              key: _formKey,
              child: ListView(
                children: [
                  // HEADER ICON
                  Center(
                    child: Container(
                      height: 70,
                      width: 70,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.black, width: 2),
                      ),
                      child: const Icon(Icons.assignment,
                          color: Colors.black, size: 36),
                    ),
                  ),

                  const SizedBox(height: 20),

                  // TITLE
                  const Text(
                    "Loan Details",
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 22,
                      color: Colors.black,
                    ),
                  ),

                  const SizedBox(height: 8),

                  Text(
                    "Provide the required information to continue with your loan request.",
                    style: TextStyle(
                      color: Colors.black.withValues(alpha: 0.6),
                      fontSize: 14,
                    ),
                  ),

                  const SizedBox(height: 25),

                  // --- INPUT CARD ---
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Colors.grey[50],
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.black12),
                    ),
                    child: Column(
                      children: [
                        // Loan Amount
                        TextFormField(
                          keyboardType: TextInputType.number,
                          decoration: InputDecoration(
                            labelText: "Loan Amount",
                            labelStyle: const TextStyle(color: Colors.black),
                            prefixIcon: const Icon(Icons.payments_outlined,
                                color: Colors.black),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          onSaved: (value) =>
                              amount = double.tryParse(value ?? "0") ?? 0,
                          validator: (value) => value!.isEmpty
                              ? "Please enter loan amount"
                              : null,
                          style: const TextStyle(color: Colors.black),
                        ),

                        const SizedBox(height: 30),

                        // Loan Reason
                        DropdownButtonFormField<String>(
                          decoration: InputDecoration(
                            labelText: "Reason for Loan",
                            labelStyle: const TextStyle(color: Colors.black),
                            prefixIcon: const Icon(Icons.category_outlined,
                                color: Colors.black),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          value: selectedReason,
                          onChanged: (value) {
                            setState(() => selectedReason = value);
                          },
                          items: loanReasons
                              .map(
                                (reason) => DropdownMenuItem(
                                  value: reason,
                                  child: Text(reason,
                                      style:
                                          const TextStyle(color: Colors.black)),
                                ),
                              )
                              .toList(),
                          validator: (value) => value == null || value.isEmpty
                              ? "Please select a reason"
                              : null,
                          style: const TextStyle(color: Colors.black),
                          dropdownColor: Colors.white,
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 30),

                  // SUBMIT BUTTON
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _submitting ? null : _goToConfirmation,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.black,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        "Apply Now",
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                          fontSize: 16,
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 20),
                ],
              ),
            ),
          ),
        ),

        // --- LOADING OVERLAY ---
        if (_submitting)
          Container(
            color: Colors.black.withValues(alpha: 0.3),
            child: const Center(
              child: CircularProgressIndicator(color: Colors.black),
            ),
          ),
      ],
    );
  }

  void _goToConfirmation() async {
    int? userId = await getUserId();

    if (_formKey.currentState!.validate()) {
      _formKey.currentState!.save();

      final application = LoanApplication(
        userId: userId!,
        loanProductId: widget.loanProductId,
        loanProductTermId: widget.loanProductTermId,
        institutionId: widget.institutionId,
        amount: amount,
        reason: selectedReason!,
      );

      if (!mounted) return;

      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => LoanConfirmationScreen(
            application: application,
            onConfirm: () => _submitApplication(application),
          ),
        ),
      );
    }
  }

  Future<void> _submitApplication(LoanApplication application) async {
    setState(() => _submitting = true);

    try {
      await submitLoanApplication(application);

      setState(() => _submitting = false);

      if (!mounted) return;

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => const LoanApplicationResultScreen(
            success: true,
            message: "Your loan application was submitted successfully.",
          ),
        ),
      );

      // Navigator.pushAndRemoveUntil(
      //   context,
      //   MaterialPageRoute(builder: (_) => const HomeScreen()),
      //   (_) => false,
      // );
    } catch (e) {
      setState(() => _submitting = false);

      if (!mounted) return;

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => LoanApplicationResultScreen(
            success: false,
            message: e.toString(),
          ),
        ),
      );
    }
  }

  // Submit loan application
  Future<void> submitLoanApplication(LoanApplication application) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? token = prefs.getString('access_token');
    final response = await http.post(
      Uri.parse("$apiUrl/loan-applications"),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(application.toJson()),
    );

    if (response.statusCode != 201) {
      final Map<String, dynamic> responseData = jsonDecode(response.body);
      final String errorMessage =
          responseData['error'] ?? responseData['message'] ?? "Unknown error";
      throw Exception(errorMessage);
    }
  }

  Future<int?> getUserId() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    return prefs.getInt("user_id"); // Returns null if not found
  }
}
