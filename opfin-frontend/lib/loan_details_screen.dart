import 'package:flutter/material.dart';
import 'loan_review_screen.dart';

class LoanDetailsScreen extends StatefulWidget {
  final int amount;
  const LoanDetailsScreen({super.key, required this.amount});

  @override
  State<LoanDetailsScreen> createState() => _LoanDetailsScreenState();
}

class _LoanDetailsScreenState extends State<LoanDetailsScreen> {
  int _duration = 30; // default: 30 days

  double get interestRate => 0.12; // 12% interest example

  double get totalPayable => widget.amount + (widget.amount * interestRate);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          "Loan Details",
          style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold),
        ),
        iconTheme: const IconThemeData(color: Colors.black),
      ),
      body: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              "Choose Duration",
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 20),
            DropdownButtonFormField<int>(
              value: _duration,
              items: const [
                DropdownMenuItem(value: 14, child: Text("14 days")),
                DropdownMenuItem(value: 30, child: Text("30 days")),
                DropdownMenuItem(value: 60, child: Text("60 days")),
                DropdownMenuItem(value: 90, child: Text("90 days")),
              ],
              onChanged: (value) {
                setState(() => _duration = value!);
              },
              decoration: InputDecoration(
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                labelText: "Duration",
              ),
            ),
            const SizedBox(height: 40),
            const Text(
              "Summary",
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 20),
            _summaryRow("Amount", "UGX ${widget.amount}"),
            _summaryRow("Interest (12%)",
                "UGX ${(widget.amount * interestRate).toInt()}"),
            _summaryRow("Total Payable", "UGX ${totalPayable.toInt()}"),
            const Spacer(),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => LoanReviewScreen(
                        amount: widget.amount,
                        duration: _duration,
                        totalPayable: totalPayable.toInt(),
                      ),
                    ),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.black,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: const Text("Review Loan"),
              ),
            )
          ],
        ),
      ),
    );
  }

  Widget _summaryRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 16)),
          Text(value,
              style:
                  const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
