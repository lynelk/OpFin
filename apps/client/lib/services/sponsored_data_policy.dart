import 'package:opfin/constants.dart';

enum DataSponsorshipClass {
  sponsored,
  nonSponsored,
  unknown,
}

/// Classifies OpFin traffic without pretending to know the carrier's billing
/// treatment. Candidate OpFin hosts are marked sponsored only when the release
/// was built with an operator-confirmed sponsorship flag.
class SponsoredDataPolicy {
  static const bool carrierConfirmed = bool.fromEnvironment(
    'OPFIN_SPONSORED_DATA_CONFIRMED',
    defaultValue: false,
  );

  static const String operatorReference = String.fromEnvironment(
    'OPFIN_SPONSORED_OPERATOR_REFERENCE',
    defaultValue: '',
  );

  static const String _additionalHosts = String.fromEnvironment(
    'OPFIN_SPONSORED_HOSTS',
    defaultValue: '',
  );

  static Set<String> candidateHosts() {
    final hosts = <String>{};
    final apiHost = Uri.tryParse(apiUrl)?.host.toLowerCase();
    if (apiHost != null && apiHost.isNotEmpty) hosts.add(apiHost);
    for (final host in _additionalHosts.split(',')) {
      final value = host.trim().toLowerCase();
      if (value.isNotEmpty) hosts.add(value);
    }
    return hosts;
  }

  static DataSponsorshipClass classify(Uri uri) {
    final host = uri.host.toLowerCase();
    if (!candidateHosts().contains(host)) {
      return DataSponsorshipClass.nonSponsored;
    }
    return carrierConfirmed
        ? DataSponsorshipClass.sponsored
        : DataSponsorshipClass.unknown;
  }

  static String wireValue(DataSponsorshipClass value) => switch (value) {
        DataSponsorshipClass.sponsored => 'sponsored',
        DataSponsorshipClass.nonSponsored => 'non_sponsored',
        DataSponsorshipClass.unknown => 'unknown',
      };
}
