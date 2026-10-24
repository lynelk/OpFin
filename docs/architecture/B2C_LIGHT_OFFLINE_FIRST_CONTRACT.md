# B2C Lightweight, Offline-First Financial Life Contract

**Status:** Proposed P0 product, security and release acceptance contract; not proof of completed offline financial-life support
**Date:** 24 October 2026
**Language:** British English
**Authority:** [OpFin B2C Companion Strategy](../product/OPFIN_B2C_FINANCIAL_COMPANION_STRATEGY.md), [Financial Life Continuity and Dignity](FINANCIAL_LIFE_CONTINUITY_AND_DIGNITY.md), [Lightweight and Sponsored Data Standard](LIGHTWEIGHT_SPONSORED_DATA.md)

## 1. Constitution

**Every financial life deserves a future worth looking forward to.** Mobile data, phone cost, connectivity and access to formal financial institutions must not decide whether a person can obtain basic financial guidance.

**Offline-first** means that ordinary *non-transactional* B2C activities are designed to keep working without a live connection once required local content or records are available. It does not mean that external balances, transactions, identity verification, account creation, credit decisions, settlements, permissions or provider actions can be confirmed offline.

**Lightweight** means minimal app and transfer size, inexpensive devices, responsive UI on intermittent 2G/3G, low battery/storage impact, fast task completion, no unnecessary rich media or background polling, and an option to use simple language and low-detail layouts.

Financial decisions and earned, voluntary customer reliance are joint goals. Reliance depends on remembered and usable financial context, dependable offline planning, transparent state and timely synchronisation, not lock-in or high-frequency engagement.

**Whole end-user scope:** This architecture applies to *every* financial-life function, not just calculators, budget tracking and Home. Its domain-by-domain application is defined in the [Complete B2C End-User Scope](../product/OPFIN_B2C_COMPLETE_END_USER_SCOPE.md): borrowing, Essentials, saving, investing, protection, payments, participatory/asset finance, statements, groups, clubs, SACCOs, People, support, account rights and future add-ons. Offline capability must coexist with **complete, non-regressed online functionality**.

## 2. User-visible offline capability matrix

| Experience | Airplane mode / no connection | Needs network and server | Offline state label |
| --- | --- | --- | --- |
| Public basic financial learning | Included, versioned local content | Updates / supplemental media | Available offline; content last refreshed |
| Guest/first-value money check | Local calculator; no login, provider or persistence needed | Optional account save, if implemented and consented | This result is calculated only from numbers you enter |
| Personal budgeting and planning | Use locally cached *authorised* snapshot or a clearly marked draft | Authoritative source refresh and persistence | Draft or last synced at [date/time] |
| Money in/out records | Create, edit, undo *drafts* in protected per-user local store | Server validation, accepted record, reconciliation | Not yet synchronised / synced / needs review |
| Goals and commitments | Review permitted cache; local edits as drafts | Server final state | Pending sync; not a deposit or bill payment |
| Financial Compass | Show last valid source snapshot with age + stale warning; local what-if is separate | New authoritative balances and next-step reasons | As of [time]; some information may have changed |
| Learning and scenario calculators | Locally available after install/cache | Optional update/online specialised advice | Offline estimate, based on recorded or entered assumptions |
| Alerts and reminders | Device-local events where lawful and consented, without claiming financial event completion | Updated schedules, provider changes | Calendar reminder, not provider confirmation |
| Account signup / recovery / KYC | Explain what is required; no false offline authentication | OTP, server sessions, third-party evidence | Connection needed |
| Credit, insurance, savings, investment and payments | Read permitted last-known information, draft only where explicitly authorised | All eligibility, offers, authority, money movement and finality | Cannot complete while offline |
| Financial Spaces switch | Only previously authorised, non-expired, context-isolated cached data | Server-authoritative permissions and membership changes | Offline view, permissions may have changed |
| Help and complaints | Static guidance, draft message with protection | Submission, acknowledgement, reference | Not yet sent |

The minimum P0 test case requires an offline **guest money check** and an offline **authenticated personal draft or cached view**, with strict labels. Guest check calculations are NOT automatically saved.

## 3. Local data security

- Financial records and draft journals are sensitive. Never put raw money diaries, obligations, income or debt details in SharedPreferences, the generic existing offline queue, analytics, notification payloads or logs. Use vetted encrypted, user-scoped local storage with an OS-keystore-backed encryption key, threat model and key rotation/recovery design.
- If secure offline financial storage is unavailable, keep the screen useful through **ephemeral local calculator inputs** and cached educational content without promising saved offline transactions.
- Never locally queue OTPs, PINs, NIN, ID/selfie artefacts, access/refresh tokens, provider payloads or regulated money instructions. Follow the existing forbidden-payload controls.
- Require re-authentication or biometric/device unlock for sensitive cached views according to reviewed session policy. Clear or cryptographically revoke user-scoped caches on logout, account removal, device reassignment or tenant/Space delinking.
- Limit stored time range, number of drafts and total bytes. Publish clear storage usage, retention and purge controls.
- Every persisted draft should have owner identity, local event ID, device ID, context/Space ID, created/updated time, schema version, idempotency reference and source. Treat a device clock as fallible.
- Financial Space caches must be physically/logically partitioned by user and Space. An expired membership must not reveal new or previously disallowed information; validate permitted caching scope at each sync.
- On shared devices, prevent personal financial amounts from appearing in unlocked notifications and avoid sensitive screenshots where platform-supported and justified.
- Assess loss/theft, shared-phone usage, rooted devices, low storage, interrupted upgrades, OS backups and reinstall.

## 4. Sync architecture and state machine

The API remains the source of truth. The client should have an explicitly supported operation allowlist for safe drafts; a generic queue endpoint must never be assumed to support arbitrary financial writes.

**Local draft → Queued → Sending → Server accepted / Needs review / Rejected / Retry possible.**

- A write gets a stable idempotency key and local ID before initial send. Retries reuse the same key. Batches are bounded by the existing [Lightweight Standard](LIGHTWEIGHT_SPONSORED_DATA.md).
- Never auto-retry ambiguous financial transactions or switch providers after a timeout. Obtain provider reconciliation evidence first.
- Conflicts are displayed and resolved intentionally; server-accepted financial history is not silently overwritten by stale local data.
- Show last successful sync time, queued count, sync progress and data usage without revealing sensitive details.
- Reconnect should sync only permitted drafts with explicit backoff and cellular-cost controls; no high-frequency polling, unsolicited SDKs or oversized attachments.
- Offline calculations must never update authoritative balances, credit scores, verified savings, provider statements or repayment status.
- Schema migrations must preserve user draft history with backups/recovery tests or safely fail with an export mechanism where lawful.

## 5. P0 performance and inclusion budgets

Adopt, do not replace, the existing [Lightweight and Sponsored Data Standard](LIGHTWEIGHT_SPONSORED_DATA.md), including its initial ≤50 KB authenticated launch, ≤100 KB Home refresh, ≤15 MB routine active-user month (excluding KYC and store updates), bounded offline batches and Android package/artefact gates. These are engineering targets subject to device/carrier verification, not proven consumption figures.

Additional P0 user acceptance:
- Core calculator starts and calculates in airplane mode without network calls.
- Common input form can be completed with one hand, screen reader and large text.
- Low-memory Android test device remains usable with no unbounded cache or battery-draining retry loop.
- Customer can see when a snapshot is stale and distinguish draft from settled/confirmed.
- Metered data measurements by journey; offline-capable journeys must not silently download videos, maps or unapproved fonts.
- Localisation may include English plus user-tested Ugandan language content as appropriate, but no translation should obscure fees or financial status.
- Disabled mobile data, flapping connection, insufficient storage, forced process termination, clock change, key loss, duplicate reconnect, logout/user switch and revoked Space permission have explicit negative tests.
- The external carrier, not OpFin, determines actual zero-rating eligibility. Never claim free/sponsored data without operator approval.

## 6. Phase gates and realistic status

**Immediate deliverable:** a standalone, ephemeral offline money-check utility requiring no provider or registration, surfaced from onboarding and authenticated Home, including when Home data cannot be fetched. This is not complete offline-first financial-life functionality.

**P0 follow-on:** privacy-reviewed encrypted local journal, secure offline budget/goals drafts, optional local review and server-authorised syncing for explicitly approved non-financial-finality operations. Requires an accepted API contract, conflict matrix, release tests and accessibility review.

**P1:** stale-labelled private snapshot with strict user/Space scoping and quick local insight, selective delta sync, more useful local reminder and contextual education library.

**P2:** advanced offline financial scenarios, import review and specialist Space capability where permission and encryption requirements are met.

**Do not announce 'fully offline-first'** until P0 storage, data isolation, queue allowlist, contract tests and real-device release acceptance are complete. Not having these foundations must not stop the ephemeral, offline-safe guest planner from shipping.
