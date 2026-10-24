/// Device-wallet readiness is display policy only, never payment authority.
/// The backend and contracted acquiring provider must separately confirm
/// merchant account, allowed countries, product, amount, token and settlement.
enum ExpressWallet { applePay, googlePay }

enum PaymentPurpose {
  goodsOrServicesOutsideApp,
  permittedFinancialService,
  loanRepayment,
  digitalSubscription,
  depositOrCustody,
}

class ExpressWalletEvidence {
  const ExpressWalletEvidence({
    required this.wallet,
    required this.country,
    required this.purpose,
    required this.deviceSupported,
    required this.issuerAndCountrySupported,
    required this.providerContractCertified,
    required this.merchantConfigured,
    required this.productAndCurrencyApproved,
    required this.authoritativeCheckoutReady,
  });

  final ExpressWallet wallet;
  final String country;
  final PaymentPurpose purpose;
  final bool deviceSupported;
  final bool issuerAndCountrySupported;
  final bool providerContractCertified;
  final bool merchantConfigured;
  final bool productAndCurrencyApproved;
  final bool authoritativeCheckoutReady;

  /// The Uganda launch has no certified in-app wallet acquiring integration.
  /// It also must not interpret Google Wallet storage as contactless payments.
  /// An explicit future policy update and independently verified evidence are
  /// required before any UG express-wallet button can be displayed.
  bool get mayShowCheckout {
    if (country.toUpperCase() == 'UG') return false;
    if (purpose == PaymentPurpose.digitalSubscription ||
        purpose == PaymentPurpose.depositOrCustody) return false;
    return deviceSupported &&
        issuerAndCountrySupported &&
        providerContractCertified &&
        merchantConfigured &&
        productAndCurrencyApproved &&
        authoritativeCheckoutReady;
  }

  String get unavailableMessage => country.toUpperCase() == 'UG'
      ? 'Apple Pay and Google Pay checkout are not activated for OpFin in Uganda.'
      : 'This payment method needs an approved device, country, provider, merchant, currency and transaction.';
}
