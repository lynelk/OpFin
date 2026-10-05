import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/provider_statements_screen.dart';

const _space = <String, dynamic>{'id': 7, 'name': 'Synthetic space', 'currency': 'UGX', 'type': 'personal'};

void _tallScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(1080, 4000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  test('statement wording never describes a document or customer as suspicious', () {
    const codes = {
      'extraction': ['pending', 'complete', 'partial', 'not_yet_supported', 'not_processed', 'unreadable'],
      'financial_consistency': ['pending', 'consistent', 'discrepancies', 'unable_to_assess'],
      'source_authenticity': ['unconfirmed'],
      'review': [
        'pending', 'no_issues_detected_by_executed_checks', 'needs_review', 'not_applicable', 'resolved_with_reasons',
        'rejected_with_reasons', 'resubmission_requested', 'original_requested', 'issuer_verification_requested',
        'escalated', 'appealed', 'superseded_by_resubmission',
      ],
    };
    for (final entry in codes.entries) {
      for (final code in entry.value) {
        final label = statementLabel(entry.key, code).toLowerCase();
        expect(label, isNot(contains('fraud')));
        expect(label, isNot(contains('suspicious')));
        expect(label, isNot(contains('_')), reason: 'every known code has plain wording');
      }
    }
    expect(statementLabel('source_authenticity', 'unconfirmed'), 'Not confirmed by your provider');
  });

  testWidgets('results show plain assurance, the next step and the appeal action', (tester) async {
    _tallScreen(tester);
    await tester.pumpWidget(MaterialApp(
      home: ProviderStatementDetailScreen(
        space: _space,
        statementId: 11,
        load: () async => {
          'status_explanation': 'Read and checked for consistency. The issuer has not confirmed this document.',
          'assurance': {
            'extraction': 'complete',
            'financial_consistency': 'discrepancies',
            'source_authenticity': 'unconfirmed',
            'review': 'rejected_with_reasons',
            'document_signals': ['editing_software_metadata'],
          },
          'analysis': {
            'findings': [
              {'code': 'amount_balance_discrepancy', 'severity': 'review', 'source_line': 6},
              {'code': 'closing_balance_mismatch', 'severity': 'review', 'source_line': null},
            ],
          },
          'next_action': 'This statement cannot be used. If you think this is wrong, you can appeal once.',
          'reviews': [
            {'decision': 'rejected', 'reason_code': 'issuer_confirmed_alteration', 'decided_at': '2026-09-27 10:00:00'},
          ],
          'can_appeal': true,
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Read in full'), findsOneWidget);
    expect(find.text('Some figures do not add up'), findsOneWidget);
    expect(find.text('Not confirmed by your provider'), findsOneWidget);
    expect(find.text('2 items to check'), findsOneWidget);
    expect(find.text('This file was edited or re-saved'), findsOneWidget);
    expect(find.textContaining('you can appeal once'), findsOneWidget);
    expect(find.text('Appeal this decision'), findsOneWidget);
    expect(find.textContaining('fraud', findRichText: true), findsNothing);
  });

  testWidgets('the upload form validates before anything is sent', (tester) async {
    _tallScreen(tester);
    Map<String, Object?>? sent;
    await tester.pumpWidget(MaterialApp(
      home: ProviderStatementUploadScreen(
        space: _space,
        today: DateTime(2026, 9, 26),
        loadIssuers: () async => [
          {'id': 3, 'legal_name': 'Synthetic Bank Uganda Limited'},
        ],
        pickFile: () async => const PickedStatement(name: 'statement.pdf', size: 2048, path: '/tmp/statement.pdf'),
        uploader: ({
          required int spaceId,
          required int issuerVersionId,
          required String currency,
          required String periodStart,
          required String periodEnd,
          required String accountReference,
          required String authorityReference,
          required String authorityExpiresAt,
          required String filePath,
          required String fileName,
          required String idempotencyKey,
          int? supersedesStatementId,
        }) async {
          sent = {
            'issuer': issuerVersionId, 'currency': currency, 'start': periodStart, 'end': periodEnd,
            'account': accountReference, 'file': fileName, 'key': idempotencyKey,
          };
          return {'id': 21};
        },
      ),
    ));
    await tester.pumpAndSettle();

    final submit = find.widgetWithText(FilledButton, 'Upload statement');
    await tester.ensureVisible(submit);
    await tester.tap(submit);
    await tester.pumpAndSettle();
    expect(find.text('Enter the account name or number.'), findsOneWidget);
    expect(sent, isNull);

    await tester.enterText(find.widgetWithText(TextFormField, 'Account name or number'), 'Main savings account');
    await tester.ensureVisible(submit);
    await tester.tap(submit);
    await tester.pumpAndSettle();
    expect(find.text('Choose your bank or mobile money provider.'), findsOneWidget);

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Synthetic Bank Uganda Limited').last);
    await tester.pumpAndSettle();
    for (final label in ['First date on the statement', 'Last date on the statement']) {
      await tester.tap(find.text(label));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
    }
    final choose = find.text('Choose PDF or CSV file');
    await tester.ensureVisible(choose);
    await tester.tap(choose);
    await tester.pumpAndSettle();
    await tester.ensureVisible(submit);
    await tester.tap(submit);
    await tester.pumpAndSettle();
    expect(find.text('Confirm that you may share this account for analysis.'), findsOneWidget);
    expect(sent, isNull);

    final confirm = find.byType(CheckboxListTile);
    await tester.ensureVisible(confirm);
    await tester.tap(confirm);
    await tester.pumpAndSettle();
    await tester.ensureVisible(submit);
    await tester.tap(submit);
    await tester.pumpAndSettle();
    expect(sent, isNotNull);
    expect(sent!['issuer'], 3);
    expect(sent!['currency'], 'UGX');
    expect(sent!['start'], '2026-08-27');
    expect(sent!['end'], '2026-09-26');
    expect(sent!['file'], 'statement.pdf');
    expect(sent!['key'].toString(), startsWith('statement-'));
  });
}
