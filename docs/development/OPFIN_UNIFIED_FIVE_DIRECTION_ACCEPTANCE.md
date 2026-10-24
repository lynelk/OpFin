# OpFin Unified Five-Direction Functional Acceptance and Release Register

**Status:** Review candidate, not a production or provider activation certificate  
**Date:** 24 October 2026 • English (UK)  
**Canonical strategy:** [Unified Five-Direction Strategy](../product/OPFIN_UNIFIED_FIVE_DIRECTION_STRATEGY.md)  
**Existing delivery contracts:** [B2C complete end-user](../product/OPFIN_B2C_COMPLETE_END_USER_SCOPE.md), [B2C low-data/offline](../architecture/B2C_LIGHT_OFFLINE_FIRST_CONTRACT.md), [B2B institution-specific](OPFIN_B2B_INSTITUTIONAL_ACCEPTANCE_PROGRAMME.md).

## One release register with five independent acceptance paths

| Direction | Source action and minimum user value | Code and automated evidence | Test not completed by the current scope | Additional live gate |
| --- | --- | --- | --- | --- |
| **B2C** | Guest sees useful money check with no login/provider/network; personal authorised finance and planning remain intact | `apps/client/lib/offline_money_check_screen.dart`, onboarding/Home integration, `apps/client/test/offline_money_check_test.dart` and `onboarding_screen_test.dart` | Physical low-spec Android/iOS data/performance; full encrypted offline journal; complete personal/provider journey parity | Actual lender/saver/investment/insurer money operation and platform-channel policy |
| **B2B** | Institution admin configures Space and onboarding without another institution; ordinary member cannot see KYB; submitted review locked | `OrganisationJourneyController`, `WebOnboardingAcceptanceTest`; club, SACCO, group etc own suite | Full institution product-cycle and finance controls per 12-type register; real data imports and service economics | Legal authority, custody, signed institutional contract, pricing where appropriate |
| **B2B2C** | Member enters only own authorised Space, sees approved records and can leave/revoke access | `FinancialSpaceController`, existing Workspace/client membership roles and denial tests | Exact member/client parity, independent branded host DNS/TLS and full revocation/retained-record UAT | Real product/provider activation if regulated money actions |
| **C2B** | Customer owns need/request in selected Space; institution/product matching is not automatic approval | `FinancingController` and `FinancingService`; authorised `FinancialIntent` source | Current-head full quote/application, affordability, reconsideration, actual settlement/device evidence | Lender/institution product/credit/market approval and signed terms |
| **C2B2B** | Approved partner passes bounded source request; customer confirms or declines to make `FinancialIntent`, provider assessment remains distinct | `PartnerFinancialIntentController`; `PartnerFinancialIntentWorkflowTest`; reviewed API contract | Arbitrary multi-hop onward processing, second institution mandates, cross-provider recovery and UAT | Both institution legal contracts, exact customer data permissions, approved provider and full financial finality |

The existing `GET /api/service-registry` now carries all five **discovery-only** relationship definitions. Its metadata cannot authorise a financial event or be treated as a statement that five corresponding regulated business journeys are live. See `OpFinServiceRegistryTest`.

## Shared cross-mode non-regression and isolation matrix

| Invariant | Positive case | Required negative case |
| --- | --- | --- |
| One individual, many Spaces | B2C user also SACCO member/club treasurer; correct context switching | Ordinary SACCO/club/employer user reads Personal accounts, KYC or unrelated membership |
| KYB and institution onboarding | Authorised institution owner saves preserved draft, submits for review and reads own evidence | Ordinary member reads registration document/evidence; stranger reads Space; submitted case modified back to draft |
| Referral and customer authority | Partner request bound to source+customer; customer confirms eligible Space and preference | Partner sees credit decision/Personal Space; changes financial purpose on replay; forwarding without customer consent |
| Money boundary | Contracted provider executes original idempotent instruction, confirmed via independent external evidence, posted and reconciled once | Quote, internal approval, HTTP acknowledgement or offline draft shown as settled; ambiguous request retried with new key |
| Offline/light usage | Guest money check computes temporary estimate without reading/storing secrets, even without network | A cached value represented as provider-confirmed; lost account isolation on logout/switch; unauthorised financial pending action replayed |
| Membership governance | Correct maker/checker, former-member access to own permitted history | Same person self-approves; expired member approves; new role inherits previous Space data |
| Pricing | Contracted explicit charge or zero fee with correct principal/premium/capital classification | Missing pricing silently set to zero; capital booked as platform income |
| Provider independence | Unavailable specific lender/payout does not prevent education, planning and independently useful institutional records | Complete product crashes or lies about settlement because third-party service is offline |

## Recording evidence

For each relationship path, record separately:
- repo candidate commit SHA, workflow run, migration version and rollback;
- API/web/mobile test outputs and exact user institution/Space role;
- successful/failed consent, identity, entitlement, contract and provider-state check;
- period/currency/amount correctness, source-of-truth and reconciliation;
- low-end real-device, accessibility, data-use and multi-session behaviour;
- institution-specific legal/provider/custody/market/store evidence and commercial approval;
- operational sign-off (support, incident response, retention/portability and release authority).

### Five readiness labels, never averaged

1. **Specified:** agreed function and controlling source documented.
2. **Source implemented:** code and a valid role-scoped interaction exists.
3. **Tested:** exact SHA relevant automated/UAT evidence is passed.
4. **Provider activated:** current applicable contracts/regulator/financial-service evidence is verified (only where necessary).
5. **Operationally accepted:** release, finance, security, commercial and customer/member sign-offs are complete.

A management-only B2B/Savings Group tool may be complete without a regulated financial provider; in that case mark money-provider activation **not applicable**, not 'passed'. Never use an all-model green badge based on unrelated institutions or the same person's login alone.

## Current integration scope

This combined change intentionally delivers **code-level integration and shared controls** plus unified product/acceptance documentation. It does **not** claim full live multi-institution C2B2B, licensed SACCO deposits, automated Investment Club payout, certified insurance policy, full offline journal, statutory payroll, all-device release or unrestricted white-label routes. Those have existing independently scoped workstreams and require their own finance/security/legal/device evidence.

The next full integration exercise should use:
1. a consumer-only guest/offline user with no provider;
2. a real-like imported Investment Club from messy XLSX, including member capital and independent approvals;
3. a SACCO with member and treasurer roles, source-matched records and regulator/settlement evidence **only when available**;
4. a Savings Group with offline draft pledge and separate verified transfer;
5. a partner referral accepted by customer into the correct Space, followed by *distinct* licensed provider decision.

Use synthetic records for reproducible non-money tests; they never prove a live institution, actual custody or settlement.
