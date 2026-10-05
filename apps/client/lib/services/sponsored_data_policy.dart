import 'package:opfin/constants.dart';

enum DataSponsorshipClass {
  sponsored,
  nonSponsored,
  unknown,
}

/// Classifies OpFin traffic without pretending to know the carrier's billing
/// treatment. Candidate OpFin origins are marked sponsored only when the release
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

  static const String _additionalOrigins = String.fromEnvironment(
    'OPFIN_SPONSORED_ORIGINS',
    defaultValue: '',
  );

  static Set<String> candidateOrigins() {
    final origins = <String>{};
    final apiOrigin = _normalisedOrigin(Uri.tryParse(apiUrl));
    if (apiOrigin != null) origins.add(apiOrigin);

    for (final rawOrigin in _additionalOrigins.split(',')) {
      final origin = _normalisedOrigin(Uri.tryParse(rawOrigin.trim()));
      if (origin != null) origins.add(origin);
    }
    return origins;
  }

  static DataSponsorshipClass classify(Uri uri) {
    final origin = _normalisedOrigin(uri);
    if (origin == null || !candidateOrigins().contains(origin)) {
      return DataSponsorshipClass.nonSponsored;
    }
    return carrierConfirmed
        ? DataSponsorshipClass.sponsored
        : DataSponsorshipClass.unknown;
  }

  static String? _normalisedOrigin(Uri? uri) {
    if (uri == null || uri.host.isEmpty) return null;

    final scheme = uri.scheme.toLowerCase();
    if (scheme != 'https' && scheme != 'http') return null;

    final effectivePort = uri.hasPort
        ? uri.port
        : scheme == 'https'
            ? 443
            : 80;

    return '$scheme://${uri.host.toLowerCase()}:$effectivePort';
  }

  static String wireValue(DataSponsorshipClass value) => switch (value) {
        DataSponsorshipClass.sponsored => 'sponsored',
        DataSponsorshipClass.nonSponsored => 'non_sponsored',
        DataSponsorshipClass.unknown => 'unknown',
      };
}
