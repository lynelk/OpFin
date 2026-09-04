import 'package:flutter/material.dart';

class FaqsScreen extends StatelessWidget {
  const FaqsScreen({super.key});

  final List<Map<String, String>> faqs = const [
    {
      "question": "What is a loan?",
      "answer":
          "A loan is an amount of money borrowed from a financial institution or lender that must be repaid with interest over a specified period."
    },
    {
      "question": "Who can apply for a loan?",
      "answer":
          "Anyone who meets the lender's eligibility criteria, such as age, income, and credit score, can apply for a loan."
    },
    {
      "question": "How do I apply for a loan?",
      "answer":
          "You can apply online via our app or website by filling out the loan application form and submitting required documents."
    },
    {
      "question": "What documents are required?",
      "answer":
          "Typically, a valid ID, proof of income, bank statements, and any other documents requested by the lender are needed."
    },
    {
      "question": "How long does it take to get approved?",
      "answer":
          "Loan approval times vary by institution and type of loan, but many personal loans are approved within 24-72 hours."
    },
    {
      "question": "What is the interest rate?",
      "answer":
          "Interest rates depend on the loan type, term, and your credit profile. Please check the specific loan product for details."
    },
    {
      "question": "Can I repay my loan early?",
      "answer":
          "Most loans allow early repayment, but some may charge a prepayment fee. Check your loan agreement for terms."
    },
    {
      "question": "What happens if I miss a payment?",
      "answer":
          "Missing payments may result in penalties, additional interest, and a negative impact on your credit score."
    },
    {
      "question": "Can I apply for multiple loans at once?",
      "answer":
          "Yes, but approval depends on your financial profile and the lender’s policies."
    },
    {
      "question": "Where can I get help or support?",
      "answer":
          "You can contact our customer support via the app, email, or phone for assistance with your loan application."
    },
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.black),
        title: const Text(
          "FAQs",
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: Colors.black,
          ),
        ),
        centerTitle: false,
        foregroundColor: Colors.black,
      ),
      body: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
        itemCount: faqs.length,
        itemBuilder: (context, index) {
          final faq = faqs[index];

          return Container(
            margin: const EdgeInsets.only(bottom: 14),
            decoration: BoxDecoration(
              color: Colors.grey[100],
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.grey[300]!),
            ),
            child: Theme(
              // Removes the default splash + arrow color
              data: Theme.of(context).copyWith(
                dividerColor: Colors.transparent,
                splashColor: Colors.transparent,
                highlightColor: Colors.transparent,
                expansionTileTheme: const ExpansionTileThemeData(
                  iconColor: Colors.black,
                  collapsedIconColor: Colors.black,
                ),
              ),
              child: ExpansionTile(
                tilePadding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                childrenPadding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                title: Text(
                  faq['question']!,
                  style: const TextStyle(
                    color: Colors.black,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
                children: [
                  Text(
                    faq['answer']!,
                    style: const TextStyle(
                      color: Colors.black87,
                      fontSize: 15,
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
