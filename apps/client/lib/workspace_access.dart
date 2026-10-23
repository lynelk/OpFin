/// UI hints provided by the authenticated Financial Spaces endpoint.
/// These never replace server-side checks on money or membership mutations.
class WorkspaceAccess {
  const WorkspaceAccess._();

  static Map<String, dynamic> info(Map<String, dynamic> space) =>
      (space['workspace'] as Map?)?.cast<String, dynamic>() ??
      const <String, dynamic>{};

  static bool canViewFinances(Map<String, dynamic> space) =>
      info(space)['can_view_finances'] == true;

  static bool canEditFinances(Map<String, dynamic> space) =>
      info(space)['can_edit_finances'] == true;

  static bool canManageMembers(Map<String, dynamic> space) =>
      info(space)['can_manage_members'] == true;

  static bool canOpenPeople(Map<String, dynamic> space) =>
      info(space)['can_open_people_connections'] == true;

  static bool canManagePeople(Map<String, dynamic> space) =>
      info(space)['can_manage_people'] == true;

  static String label(Map<String, dynamic> space) {
    final fromServer = info(space)['label']?.toString();
    if (fromServer != null && fromServer.isNotEmpty) return fromServer;
    switch (space['type']?.toString()) {
      case 'personal': return 'Personal';
      case 'household': return 'Household';
      case 'savings_group': return 'Savings Club / VSLA';
      case 'vsla': return 'VSLA';
      case 'sacco': return 'SACCO';
      case 'investment_club': return 'Investment Club';
      case 'business': return 'Business';
      case 'investment_fund': return 'Investment Fund';
      default: return 'Organisation';
    }
  }

  /// Common module discovery for all supported community-finance spaces.
  /// This is a presentation catalogue, not permission to transact.
  /// All financial actions still require server-side product, regulatory,
  /// membership and maker-checker authorisation.
  static const List<String> groupModules = <String>[
    'Membership and KYC',
    'Savings and contributions',
    'Shares and ownership',
    'Member loans and repayments',
    'Investment portfolio',
    'Dividends and profit distribution',
    'Meetings and resolutions',
    'Loan approval committees',
    'Member statements',
    'Accounting and reconciliation',
    'Savings products and deposits',
    'Group guarantees',
    'Fines and penalties',
    'Financial and regulatory reports',
    'Data import and migration',
  ];

  static bool isFinancialGroup(Map<String, dynamic> space) =>
      const <String>{'investment_club', 'sacco', 'savings_group', 'vsla'}
          .contains(space['type']?.toString());

  static List<String> availableGroupModules(Map<String, dynamic> space) =>
      isFinancialGroup(space) ? groupModules : const <String>[];

  static String role(Map<String, dynamic> space) =>
      (space['role']?.toString() ?? 'member').replaceAll('_', ' ');

  static String accessDescription(Map<String, dynamic> space) {
    if (canEditFinances(space)) return 'Manage financial records';
    if (info(space)['can_view_finances'] == true) return 'View financial records';
    return 'Membership only';
  }
}
