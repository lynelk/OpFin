class LoanApplication {
  final int userId;
  final int loanProductId;
  final int loanProductTermId;
  final int? institutionId;
  final double amount;
  final String reason;
  final String distributionChannel;

  LoanApplication({
    required this.userId,
    required this.loanProductId,
    required this.loanProductTermId,
    this.institutionId,
    required this.amount,
    required this.reason,
    required this.distributionChannel,
  });

  Map<String, dynamic> toJson() {
    return {
      "user_id": userId,
      "loan_product_id": loanProductId,
      "loan_product_term_id": loanProductTermId,
      "institution_id": institutionId,
      "amount": amount,
      "reason": reason,
      "distribution_channel": distributionChannel,
    };
  }
}
