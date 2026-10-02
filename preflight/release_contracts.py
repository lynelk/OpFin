from pathlib import Path
import plistlib
import re

# Preserve the registered application identity in the unsigned compile gate.
p = Path('.github/workflows/ci.yml')
s = p.read_text().replace('OPFIN_IOS_BUNDLE_ID=co.opfin.ci bash tool/prepare_app_store.sh', 'bash tool/prepare_app_store.sh')
s = s.replace('needs: [layout, repo-security, financial-control-review, api, api-assets, web, client-android, client-ios]',
              'needs: [publication-readiness, layout, repo-security, financial-control-review, api, api-assets, web, client-android, client-ios]')
p.write_text(s)

# Required-reason APIs used for app-private export cleanup and preferences.
privacy = {'NSPrivacyAccessedAPITypes': [
    {'NSPrivacyAccessedAPIType': 'NSPrivacyAccessedAPICategoryFileTimestamp', 'NSPrivacyAccessedAPITypeReasons': ['C617.1']},
    {'NSPrivacyAccessedAPIType': 'NSPrivacyAccessedAPICategoryUserDefaults', 'NSPrivacyAccessedAPITypeReasons': ['CA92.1']},
]}
p = Path('apps/client/ios/Runner/PrivacyInfo.xcprivacy')
p.write_bytes(plistlib.dumps(privacy, fmt=plistlib.FMT_XML, sort_keys=False))
p = Path('apps/client/ios/Runner.xcodeproj/project.pbxproj')
s = p.read_text()
if 'PrivacyInfo.xcprivacy' not in s:
    s = s.replace('/* Begin PBXBuildFile section */', '/* Begin PBXBuildFile section */\n\t\tA01420012026100200000001 /* PrivacyInfo.xcprivacy in Resources */ = {isa = PBXBuildFile; fileRef = A01420012026100200000002 /* PrivacyInfo.xcprivacy */; };')
    s = s.replace('/* Begin PBXFileReference section */', '/* Begin PBXFileReference section */\n\t\tA01420012026100200000002 /* PrivacyInfo.xcprivacy */ = {isa = PBXFileReference; lastKnownFileType = text.xml; path = PrivacyInfo.xcprivacy; sourceTree = "<group>"; };')
    needle = '\t\t\t\t97C147021CF9000F007C117D /* Info.plist */,'
    assert needle in s
    s = s.replace(needle, needle + '\n\t\t\t\tA01420012026100200000002 /* PrivacyInfo.xcprivacy */,')
    needle = '\t\t\t\t97C146FE1CF9000F007C117D /* Assets.xcassets in Resources */,'
    assert needle in s
    s = s.replace(needle, needle + '\n\t\t\t\tA01420012026100200000001 /* PrivacyInfo.xcprivacy in Resources */,')
p.write_text(s)

# Keep API errors typed; do not pass unvalidated arbitrary fields as error maps.
p = Path('apps/web/src/lib/api/account.ts')
s = p.read_text().replace('    object(payload.errors) ? payload.errors : {});', '    {});')
p.write_text(s)
p = Path('apps/web/src/app/(portal)/account/delete/page.tsx')
s = p.read_text().replace('<StateNotice state="empty" message="Your selected optional data', '<StateNotice state="success" message="Your selected optional data')
p.write_text(s)

contracts = r'''

## Payroll, partner referral and account-deletion release contracts (2 October 2026)

These are source/release-candidate contracts, not evidence of financial-provider activation or production deployment.

| Method | Endpoint | Contract |
| --- | --- | --- |
| GET | `/api/account/deletion-readiness` | Current authenticated structured blockers, optional categories and retention disclosure |
| DELETE | `/api/account` | PIN/password re-authentication and `confirmation=DELETE`; `completed` is the only successful closure result; any obligation returns HTTP 409 `blocked_obligations` immediately |
| DELETE | `/api/account/data` | Re-authentication, `confirmation=DELETE_DATA` and `data_categories`; explicit `data_deleted` keeps the account active |
| GET | `/api/financing-applications` | Current customer's financing applications |
| GET/POST | `/api/payroll-deduction/cases` | Read/start an eligible salary-finance case inside an active authorised Financial Space |
| GET | `/api/payroll-deduction/cases/{case}` | Customer-owned case and attempt-specific reconciliation states |
| GET | `/api/payroll-deduction/provider-capability` | Honest inactive/live adapter capability, not fabricated PDMS certification |
| POST | `/api/payroll-deduction/cases/{case}/undertaking` | Atomically record the case/reference/amount-bound payroll undertaking and request reservation; `authorised=true`, positive `requested_deduction_minor`, optional agreement reference |
| POST | `/api/payroll-deduction/cases/{case}/reservation` | Compatibility request requiring a current case-bound undertaking consent; generic credit consent is insufficient |
| POST | `/api/payroll-deduction/cases/{case}/cancel` | Local cancellation only before uncertain/external reservation exposure; otherwise `cancellation_pending` until evidenced release |
| GET | `/api/operations/payroll-deduction/cases` | Authorised operations queue |
| GET | `/api/operations/payroll-deduction/cases/{case}` | Operations case, immutable transition evidence and per-attempt results |
| POST | `/api/operations/payroll-deduction/cases/{case}/affordability` | Record affordability evidence and buy-off exception, not an invented external check |
| POST | `/api/operations/payroll-deduction/cases/{case}/reservation` | Positive confirmation requires reservation/agreement references and a current undertaking |
| POST | `/api/operations/payroll-deduction/cases/{case}/key-facts` | Current reservation and mandate required; retain submitted facts |
| POST | `/api/operations/payroll-deduction/cases/{case}/vote-decision` | Positive approval checks locked reservation expiry and current undertaking |
| POST | `/api/operations/payroll-deduction/cases/{case}/payroll-submission` | Government Code 482; increment submission attempt, preserve prior attempts |
| POST | `/api/operations/payroll-deduction/cases/{case}/payroll-result` | Period must match submission; each attempt retains original outcome, amounts and provider evidence |
| POST | `/api/operations/payroll-deduction/cases/{case}/amend` | Return to reservation-pending, never manufacture renewed external reservation |
| POST | `/api/operations/payroll-deduction/cases/{case}/reconcile` | Derive expected amount from recorded result; operator cannot force a match by supplying an expected amount |
| POST | `/api/operations/payroll-deduction/cases/{case}/cancellation-release` | Require explicit released status and recorded release reference before closure |
| POST | `/api/partner/financial-intents/{customer}` | Governed active partner source binding, mandatory stable idempotency key, no financial commitment on customer's behalf |
| GET | `/api/partner-financial-intents` | Current customer's referrals |
| POST | `/api/partner-financial-intents/{request}/confirm` | Active/nondeleted Space authority; once-only canonical intent and central audit; changed Space/principles replay rejected |
| POST | `/api/partner-financial-intents/{request}/decline` | Customer-controlled, audited decline without creating a financial intent |

All payroll writes require `Idempotency-Key`; a supplied `X-Correlation-ID` must be a UUID. Keys are bound to command, canonical payload and actor. The Flutter application stores uncertain command identity in its protected session store and reuses it after interrupted responses; changing an uncertain instruction is rejected. A plain HTTP 200 is not confirmation of account closure or financial finality.

The account blocker record contains `code`, `label`, `reference`, `status`, `amount_minor`, `currency`, `due_date` and `provider` with recorded name/phone/email/address and `direct_contact_available`. Missing contacts remain null and must be described as unrecorded. `amount_basis=contract_total_not_current_balance` explicitly identifies a canonical arrangement's contract total rather than an asserted live debt balance. Structured blocker details must not enter redirect URLs.

Closure checks include canonical financing arrangements, compatibility loans, Essentials advances and unresolved durable collection/reversal instructions, recorded personal obligations, peer commitments, savings, protection/claims, investments, capital mandates, payroll reservations and pending financial actions/movement represented in the schema. Rejection creates no misleading pending support/deletion request and does not revoke servicing access. Once resolved, the customer submits a fresh request.

Optional deletion categories are `location_context`, `financial_planning`, `linked_accounts`, `household_and_microbusiness`, and `profile_preferences`. Location deletion is restricted to the requester's optional personal discovery context; it cannot erase another member's data or regulated asset/claim evidence. Required accounting, KYC/AML, credit reporting, settlement, security, consent and audit evidence are retained.

Partner source attribution is the separately reviewed `financial_intent_source_platform` of the partner account. Existing accounts are not backfilled with invented provenance. The existing independent partner approval workflow may configure the permitted source. `ALL_SUITABLE` does not waive valid Sharia approval for Islamic products.
'''
for rel in ['apps/api/docs/api/current-endpoints.md', 'apps/api/docs/api/frontend-backend-contract.md', 'apps/api/docs/api/API_QUICK_REFERENCE.md']:
    p = Path(rel)
    p.write_text(p.read_text().rstrip() + contracts + '\n')

account_doc = '''# Account and optional-data deletion

Updated: 2 October 2026
Status: release-candidate contract; production deployment is assessed separately.

Customers may delete their account or selected optional data while keeping it. Web and Flutter use the same authenticated API. The legacy public web form verifies the supplied phone and current PIN/password before using the same account-closure service.

Full closure requires re-authentication and DELETE confirmation. OpFin checks current obligations across its represented financial domains, including canonical financing arrangements, Essentials collection exceptions, peer lending, savings, claims, investment/capital mandates and payroll reservation release. Any blocker causes immediate rejection with structured references, status, relevant amount/date and only actually recorded provider contacts. No misleading pending request is created; servicing access remains available. Resolve the obligations, then submit a fresh request.

The authenticated web `/account/delete` page supports readiness and selective-data deletion without reinstalling the app. Flutter exposes the same choices under account/privacy. Only explicit `deletion_status=completed` permits client session erasure. `data_deleted` confirms selected optional data deletion without closing the account.

Financial planning, optional links/profile preferences and requester-owned optional discovery locations can be removed. Regulated financial, KYC/AML, credit-reporting, settlement, accounting, security and audit evidence requiring lawful retention is preserved. Another user's data and retained asset/claim locations are never optional-data deletion targets.

A missing provider phone/email/address is stated as unrecorded; retain the obligation reference. A canonical contract total is labelled as such rather than presented as a current outstanding balance.
'''
Path('docs/GOOGLE_PLAY_ACCOUNT_DELETION.md').write_text(account_doc)
Path('apps/api/docs/api/ACCOUNT_AND_DATA_DELETION.md').write_text(account_doc + contracts)
for rel in ['apps/client/README.md', 'apps/web/README.md', 'apps/api/docs/api/PAYROLL_DEDUCTION.md', 'apps/api/docs/api/PARTNER_FINANCIAL_INTENTS.md']:
    p = Path(rel)
    p.write_text(p.read_text().rstrip() + '\n\n## Release-candidate contract update\n\nRead the current payroll/referral/account-deletion tables in `apps/api/docs/api/frontend-backend-contract.md`. Full deletion is immediately rejected while obligations remain; optional deletion keeps the account. Payroll undertaking and reservation are atomic and case-bound. External reservation release is evidenced before closure. No production activation is implied.\n')

runbook = '''# PR 142 release and acceptance evidence

Updated: 2 October 2026
Status: candidate validation in progress; not a completed release.

## Verification and cutover

Use one exact candidate for full API, changed-PHP formatting, dependency/security audits, Web typecheck/lint/tests/build, Flutter analysis/tests, Android/Google Play and Huawei-channel compilation, iOS unsigned compilation, migration checks, canonical documentation and deployment-contract checks. Targeted repair runs are not substitutes for this final release-candidate gate. An independent authorised human approval is required; an automated comment, the author, or a tool cannot manufacture it.

Do not deploy or call production consistent until the reviewed PR is merged, API migrations succeed, and API/web/worker/scheduler report the intended source with public health/readiness evidence. Existing Railway services only; no new persistent infrastructure or spending is authorised by this document. The earlier observed web build failed on a dependency advisory and the deployed services did not share one source revision. Record fresh observations rather than treating those historical observations as current success.

## Data and migration acceptance

Exercise rejected full closure with no pending request, subsequent fresh closure, every optional category's retention boundary, wrong-user/wrong-Space attempts, old public web phone/PIN path, expired/revoked undertaking, payroll cancellation/expiry awaiting release, same-month result attempts, unchanged immutable provider snapshots after reconciliation, changed-command/actor replay, and partner source spoofing. Repeat database-sensitive cases on PostgreSQL. Migrations are additive; old provider evidence is not invented or backfilled from mutable projections.

## Store assessment

`bash tool/build_release.sh android|huawei|ios` uses the same Flutter app and explicit distribution channel. Preserve registered Android and iOS identifiers; never silently create a new store identity. Build checks without production signing do not create publishable artifacts. Real signing identities, exact version-code availability, store declarations/screenshots, review accounts, hardware-level permissions/accessibility and non-GMS Huawei testing remain external acceptance evidence.

The iOS manifest records required reasons for app-private file timestamps and preference storage. It is not a substitute for truthful App Privacy/App Store or Play Data Safety declarations covering actual KYC/financial data and providers.

## External activation gates

Official PDMS machine-interface specification, credentials/certificates, callback/authentication requirements and certification remain required before activation. Live PDMS remains fail-closed. Genuine lender/insurer/payment/KYC/CRB/regulatory authority, independent financial review, Apple/Google/Huawei production signing and store submission access must be supplied by authorised owners. Never enable financial production merely because source code compiles.
'''
Path('docs/releases/2026-10-02-pr142-release-acceptance.md').write_text(runbook)
for rel in ['docs/manuals/OPFIN_OPERATIONAL_MANUAL.md', 'docs/manuals/OPFIN_UAT_MANUAL.md', 'docs/LAUNCH_CUSTOMER_JOURNEY.md', 'docs/governance/OPFIN_DELIVERY_FEATURE_REGISTER.md']:
    p = Path(rel)
    if p.exists():
        p.write_text(p.read_text().rstrip() + '\n\n## PR 142 candidate acceptance update (2 October 2026)\n\nAccount deletion rejects unresolved obligations immediately and preserves servicing; optional-data deletion retains the account and regulated evidence. Payroll undertaking is case-bound and atomic with reservation request; cancellation/expiry awaits evidenced provider release. Apply `docs/releases/2026-10-02-pr142-release-acceptance.md` to OPF-FEAT-0022, FIN-FOUNDATION, DOC-CONTINUITY and OPF-REL-0001. This entry is implementation/validation work, not production or store acceptance.\n')

p = Path('apps/web/docs/production/app-store-release.md')
s = p.read_text().replace('OPFIN_IOS_BUNDLE_ID=co.opfin.app bash tool/prepare_app_store.sh', 'bash tool/prepare_app_store.sh')
p.write_text(s)

# Pure script used in both preflight and release review.
Path('scripts/verify-pr142-release-contract.py').write_text(r'''#!/usr/bin/env python3
from pathlib import Path
import plistlib
import re

root = Path(__file__).resolve().parents[1]
api = (root/'apps/api/routes/api.php').read_text()
bootstrap = (root/'apps/api/bootstrap/app.php').read_text()
assert 'routes/account_deletion.php' not in bootstrap
assert not (root/'apps/api/routes/account_deletion.php').exists()
assert api.count("'/account/deletion-readiness'") == 1
assert api.count("'/account/data'") == 1
plist = plistlib.loads((root/'apps/client/ios/Runner/Info.plist').read_bytes())
assert plist['NSCameraUsageDescription'] and plist['NSPhotoLibraryUsageDescription']
privacy = plistlib.loads((root/'apps/client/ios/Runner/PrivacyInfo.xcprivacy').read_bytes())
assert len(privacy['NSPrivacyAccessedAPITypes']) == 2
project = (root/'apps/client/ios/Runner.xcodeproj/project.pbxproj').read_text()
assert 'PrivacyInfo.xcprivacy in Resources' in project
assert 'OPFIN_IOS_BUNDLE_ID=co.opfin.ci' not in (root/'.github/workflows/ci.yml').read_text()
release = (root/'apps/client/tool/build_release.sh').read_text()
assert 'channel=huawei_appgallery' in release and 'CI=false flutter build apk' in release
assert re.search(r'^version:\s*\S+\+\d+', (root/'apps/client/pubspec.yaml').read_text(), re.M)
print('PR142 route, iOS permission/manifest, identity and shared Huawei release contracts passed.')
''')
print('Canonical contracts, retention/runbook acceptance, release gates and iOS required-reason manifest prepared.')
