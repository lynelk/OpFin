const String apiUrl = String.fromEnvironment(
  'OPFIN_API_BASE_URL',
  defaultValue: 'https://opfin-production.up.railway.app/api',
);

const String appEnvironment = String.fromEnvironment(
  'OPFIN_ENVIRONMENT',
  defaultValue: 'production',
);

void validateApiConfiguration(String value, {required bool release}) {
  final endpoint = Uri.tryParse(value);
  if (endpoint == null || endpoint.host.isEmpty || endpoint.userInfo.isNotEmpty || endpoint.hasQuery || endpoint.hasFragment) {
    throw StateError('OpFin API configuration is invalid.');
  }
  if (release && endpoint.scheme != 'https') {
    throw StateError('An OpFin release requires an HTTPS API endpoint.');
  }
  if (!release && endpoint.scheme != 'http' && endpoint.scheme != 'https') {
    throw StateError('OpFin API configuration must use HTTP or HTTPS.');
  }
}
