import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/home_screen.dart';
import 'package:opfin/services/user_session.dart';
import 'dart:convert';

class LoanRepaymentScreen extends StatefulWidget {
  final int loanId;
  final int repaymentAmount;

  const LoanRepaymentScreen(
      {super.key, required this.loanId, required this.repaymentAmount});

  @override
  LoanRepaymentScreenState createState() => LoanRepaymentScreenState();
}

class LoanRepaymentScreenState extends State<LoanRepaymentScreen> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  bool _loading = false;
  String? _message;
  bool _success = false;

  Future<void> _repayLoan() async {
    setState(() {
      _loading = true;
      _message = null;
      _success = false;
    });
    final amount = int.parse(_amountController.text);
    final token = await UserSession.getAccessToken();
    final response = await http.post(
      Uri.parse('$apiUrl/loans/${widget.loanId}/repay'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({'amount': amount}),
    );
    setState(() {
      _loading = false;
      final data = jsonDecode(response.body);
      _success = data['success'] ?? false;
      _message = data['message'] ?? 'Unexpected response';
    });
    if (_success) {
      // Show success message for 2 seconds
      await Future.delayed(const Duration(seconds: 2));

      Navigator.pushAndRemoveUntil(
        // ignore: use_build_context_synchronously
        context,
        MaterialPageRoute(builder: (context) => const HomeScreen()),
        (route) => false, // removes all previous routes
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Repay Loan',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        elevation: 0,
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        iconTheme: const IconThemeData(color: Colors.black),
        foregroundColor: Colors.black,
      ),
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Icon(Icons.payments, size: 48, color: theme.primaryColor),
                const SizedBox(height: 16),
                Text(
                  'Loan Repayment',
                  style: theme.textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Text(
                  'Expected Repayment',
                  style: theme.textTheme.bodySmall,
                ),
                Text(
                  'UGX ${widget.repaymentAmount.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]},')}',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: theme.primaryColor,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 32),
                TextFormField(
                  controller: _amountController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Amount to Pay',
                    prefixIcon: Padding(
                      padding: const EdgeInsets.all(12.0),
                      child: Text(
                        'UGX',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                          color: Colors.grey[700],
                        ),
                      ),
                    ),
                    border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8)),
                    filled: true,
                    fillColor: Colors.grey[50],
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) return 'Enter amount';
                    final entered = int.tryParse(value);
                    if (entered == null) return 'Enter a valid number';
                    if (entered <= 0) return 'Amount must be positive';
                    if (entered > widget.repaymentAmount) {
                      return 'Cannot pay more than expected';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 32),
                _loading
                    ? const CircularProgressIndicator()
                    : SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.black,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            textStyle: const TextStyle(
                                fontSize: 16, fontWeight: FontWeight.bold),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8)),
                          ),
                          onPressed: () {
                            if (_formKey.currentState!.validate()) {
                              _repayLoan();
                            }
                          },
                          child: const Text('Repay'),
                        ),
                      ),
                if (_message != null) ...[
                  const SizedBox(height: 28),
                  Row(
                    children: [
                      Icon(
                        _success ? Icons.check_circle : Icons.error,
                        color: _success ? Colors.green : Colors.red,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _message!,
                          style: TextStyle(
                            color: _success ? Colors.green : Colors.red,
                            fontWeight: FontWeight.w600,
                            fontSize: 16,
                          ),
                        ),
                      ),
                    ],
                  ),
                ]
              ],
            ),
          ),
        ),
      ),
    );
  }
}
