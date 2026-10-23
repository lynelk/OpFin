import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/workspace_access.dart';

void main() {
  test('Savings Club / VSLA label preserves the savings_group type', () {
    final space = <String, dynamic>{'type': 'savings_group'};
    expect(WorkspaceAccess.label(space), 'Savings Club / VSLA');
    expect(space['type'], 'savings_group');
  });

  test('missing role metadata cannot accidentally enable financial edits', () {
    const space = <String, dynamic>{
      'type': 'business',
      'role': 'owner',
    };
    expect(WorkspaceAccess.canEditFinances(space), isFalse);
    expect(WorkspaceAccess.canManagePeople(space), isFalse);
  });

  test('uses server-issued workspace permissions', () {
    const space = <String, dynamic>{
      'type': 'business',
      'role': 'administrator',
      'workspace': <String, dynamic>{
        'label': 'Business',
        'can_edit_finances': true,
        'can_manage_people': true,
        'can_open_people_connections': true,
      },
    };
    expect(WorkspaceAccess.canEditFinances(space), isTrue);
    expect(WorkspaceAccess.canManagePeople(space), isTrue);
    expect(WorkspaceAccess.canOpenPeople(space), isTrue);
  });

  test('ordinary member is read-only when server indicates view access', () {
    const space = <String, dynamic>{
      'type': 'savings_group',
      'role': 'member',
      'workspace': <String, dynamic>{
        'can_view_finances': true,
        'can_edit_finances': false,
      },
    };
    expect(WorkspaceAccess.canViewFinances(space), isTrue);
    expect(WorkspaceAccess.canEditFinances(space), isFalse);
    expect(WorkspaceAccess.accessDescription(space), 'View financial records');
  });
}
