# Account and optional-data deletion

Updated: 2 October 2026
Status: release-candidate contract; production deployment is assessed separately.

Customers may delete their account or selected optional data while keeping it. Web and Flutter use the same authenticated API. The legacy public web form verifies the supplied phone and current PIN/password before using the same account-closure service.

Full closure requires re-authentication and DELETE confirmation. OpFin checks current obligations across its represented financial domains, including canonical financing arrangements, Essentials collection exceptions, peer lending, savings, claims, investment/capital mandates and payroll reservation release. Any blocker causes immediate rejection with structured references, status, relevant amount/date and only actually recorded provider contacts. No misleading pending request is created; servicing access remains available. Resolve the obligations, then submit a fresh request.

The authenticated web `/account/delete` page supports readiness and selective-data deletion without reinstalling the app. Flutter exposes the same choices under account/privacy. Only explicit `deletion_status=completed` permits client session erasure. `data_deleted` confirms selected optional data deletion without closing the account.

Financial planning, optional links/profile preferences and requester-owned optional discovery locations can be removed. Regulated financial, KYC/AML, credit-reporting, settlement, accounting, security and audit evidence requiring lawful retention is preserved. Another user's data and retained asset/claim locations are never optional-data deletion targets.

A missing provider phone/email/address is stated as unrecorded; retain the obligation reference. A canonical contract total is labelled as such rather than presented as a current outstanding balance.
