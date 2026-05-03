import 'package:flutter/material.dart';
import 'package:opfin/models/loan_application.dart';

class LoanConfirmationScreen extends StatefulWidget {
  final LoanApplication application;
  final VoidCallback onConfirm;

  const LoanConfirmationScreen({
    super.key,
    required this.application,
    required this.onConfirm,
  });

  @override
  State<LoanConfirmationScreen> createState() => _LoanConfirmationScreenState();
}

class _LoanConfirmationScreenState extends State<LoanConfirmationScreen> {
  bool _termsAccepted = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.black),
        title: const Text(
          "Confirm Application",
          style: TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.bold,
            fontSize: 22,
          ),
        ),
        foregroundColor: Colors.black,
      ),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            const Text(
              "Review your loan details",
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w800,
                color: Colors.black,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              "Please confirm the information below before submitting your application.",
              style: TextStyle(
                fontSize: 14,
                color: Colors.black.withOpacity(0.6),
              ),
            ),

            const SizedBox(height: 24),

            // Summary Card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: Colors.black12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 6,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Column(
                children: [
                  _infoRow(
                    "Loan Amount",
                    widget.application.amount.toStringAsFixed(2),
                    isEmphasis: true,
                  ),
                  _divider(),
                  _infoRow("Reason", widget.application.reason),
                  _infoRow("Interest Rate", '10%'),
                  _infoRow("Interest", 'UGX 700'),
                  _infoRow("Pay Back", 'UGX 7,700'),
                  _infoRow('Pay Before', '20th Dec, 2024'),
                ],
              ),
            ),
            // Terms Checkbox
            const Spacer(),

            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Checkbox(
                  value: _termsAccepted,
                  activeColor: Colors.black,
                  onChanged: (value) {
                    setState(() => _termsAccepted = value ?? false);
                  },
                ),
                const Expanded(
                  child: Text(
                    "I agree to the Terms and Conditions of the loan application.",
                    style: TextStyle(
                      color: Colors.black87,
                      fontSize: 14,
                      height: 1.4,
                    ),
                  ),
                ),
              ],
            ),
            SizedBox(height: 20),
            // Action Buttons
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(context),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      side: const BorderSide(color: Colors.black),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      "Edit",
                      style: TextStyle(
                        color: Colors.black,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Visibility(
                    visible: _termsAccepted,
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.pop(context);
                        widget.onConfirm();
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.black,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        "Confirm & Submit",
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 10),
          ],
        ),
      ),
    );
  }

  // ------------------------------------------------------------
  Widget _infoRow(String label, String value, {bool isEmphasis = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 14,
              color: Colors.black.withOpacity(0.6),
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: isEmphasis ? 18 : 15,
              fontWeight: isEmphasis ? FontWeight.w800 : FontWeight.w600,
              color: Colors.black,
            ),
          ),
        ],
      ),
    );
  }

  Widget _divider() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Divider(color: Colors.grey[300]),
    );
  }
}
