import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/workspace_access.dart';

void main() {
  test('all financial group types discover identical modules', () {
    for (final type in ['investment_club', 'sacco', 'savings_group', 'vsla']) {
      final space = <String, dynamic>{'type': type};
      expect(WorkspaceAccess.isFinancialGroup(space), isTrue);
      expect(WorkspaceAccess.availableGroupModules(space),
          WorkspaceAccess.groupModules);
      expect(WorkspaceAccess.availableGroupModules(space).length, 15);
    }
  });

  test('non-group spaces do not receive group catalogue', () {
    for (final type in ['personal', 'business', 'investment_fund']) {
      expect(WorkspaceAccess.availableGroupModules({'type': type}), isEmpty);
    }
  });

  test('module discovery never grants financial editing permission', () {
    final space = <String, dynamic>{'type': 'sacco'};
    expect(WorkspaceAccess.availableGroupModules(space), isNotEmpty);
    expect(WorkspaceAccess.canEditFinances(space), isFalse);
  });
}
