const String apiUrl = String.fromEnvironment(
  'OPFIN_API_BASE_URL',
  defaultValue: 'https://opfin-api-production.up.railway.app/api',
);

const String appEnvironment = String.fromEnvironment(
  'OPFIN_ENVIRONMENT',
  defaultValue: 'production',
);
