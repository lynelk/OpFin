import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/services/express_wallet_policy.dart';

void main() {
  ExpressWalletEvidence approved({
    ExpressWallet wallet = ExpressWallet.applePay,
    String country = 'GB',
    PaymentPurpose purpose = PaymentPurpose.goodsOrServicesOutsideApp,
    bool deviceSupported = true,
    bool issuerAndCountrySupported = true,
    bool providerContractCertified = true,
    bool merchantConfigured = true,
    bool productAndCurrencyApproved = true,
    bool authoritativeCheckoutReady = true,
  }) => ExpressWalletEvidence(
        wallet: wallet,
        country: country,
        purpose: purpose,
        deviceSupported: deviceSupported,
        issuerAndCountrySupported: issuerAndCountrySupported,
        providerContractCertified: providerContractCertified,
        merchantConfigured: merchantConfigured,
        productAndCurrencyApproved: productAndCurrencyApproved,
        authoritativeCheckoutReady: authoritativeCheckoutReady,
      );

  test('Uganda never exposes Apple Pay or Google Pay without a new approved market policy', () {
    for (final wallet in ExpressWallet.values) {
      expect(approved(country: 'UG', wallet: wallet).mayShowCheckout, false);
      expect(approved(country: 'ug', wallet: wallet).mayShowCheckout, false);
    }
  });

  test('future eligible checkout requires every contracted and authoritative gate', () {
    expect(approved().mayShowCheckout, true);
    expect(approved(wallet: ExpressWallet.googlePay).mayShowCheckout, true);
    expect(approved(deviceSupported: false).mayShowCheckout, false);
    expect(approved(issuerAndCountrySupported: false).mayShowCheckout, false);
    expect(approved(providerContractCertified: false).mayShowCheckout, false);
    expect(approved(merchantConfigured: false).mayShowCheckout, false);
    expect(approved(productAndCurrencyApproved: false).mayShowCheckout, false);
    expect(approved(authoritativeCheckoutReady: false).mayShowCheckout, false);
  });

  test('digital subscription and custody never inherit external-checkout permission', () {
    expect(approved(purpose: PaymentPurpose.digitalSubscription).mayShowCheckout, false);
    expect(approved(purpose: PaymentPurpose.depositOrCustody).mayShowCheckout, false);
  });
}
