import 'package:opfin/brand/brand_colors.dart';
import 'package:flutter/material.dart';

class LoanReviewScreen extends StatelessWidget {
  final int amount;
  final int duration;
  final int totalPayable;

  const LoanReviewScreen({
    super.key,
    required this.amount,
    required this.duration,
    required this.totalPayable,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          "Review Loan",
          style: TextStyle(color: OpFinColors.ink, fontWeight: FontWeight.bold),
        ),
        iconTheme: const IconThemeData(color: OpFinColors.ink),
      ),
      body: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              "Review Your Loan Request",
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 30),
            _infoTile("Loan Amount", "Ksh $amount"),
            _infoTile("Duration", "$duration days"),
            _infoTile("Total Payable", "Ksh $totalPayable"),
            const SizedBox(height: 40),
            const Text(
              "By continuing, you agree to the loan terms and conditions.",
              style: TextStyle(fontSize: 14, color: OpFinColors.muted),
            ),
            const Spacer(),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  // TODO: Submit loan to backend
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text("Loan Application Submitted")),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: OpFinColors.indigo,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: const Text("Submit Application"),
              ),
            )
          ],
        ),
      ),
    );
  }

  Widget _infoTile(String label, String value) {
    return Container(
      padding: const EdgeInsets.all(18),
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: OpFinColors.line),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label,
              style: const TextStyle(fontSize: 16, color: OpFinColors.muted)),
          Text(value,
              style:
                  const TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
