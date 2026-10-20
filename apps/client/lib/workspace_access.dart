/// UI hints provided by the authenticated Financial Spaces endpoint.
/// These never replace server-side checks on money or membership mutations.
class WorkspaceAccess {
  const WorkspaceAccess._();

  static Map<String, dynamic> info(Map<String, dynamic> space) =>
      (space['workspace'] as Map?)?.cast<String, dynamic>() ??
      const <String, dynamic>{};

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
      case 'savings_group': return 'Savings Groups';
      case 'sacco': return 'SACCO';
      case 'investment_club': return 'Investment Club';
      case 'business': return 'Business';
      case 'investment_fund': return 'Investment Fund';
      default: return 'Organisation';
    }
  }

  static String role(Map<String, dynamic> space) =>
      (space['role']?.toString() ?? 'member').replaceAll('_', ' ');

  static String accessDescription(Map<String, dynamic> space) {
    if (canEditFinances(space)) return 'Manage financial records';
    if (info(space)['can_view_finances'] == true) return 'View financial records';
    return 'Membership only';
  }
}
