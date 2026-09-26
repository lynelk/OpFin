# Club accounting: Web and App workflow guide

Version: 26 September 2026  
Language: English (United Kingdom)  
Status: Implementation candidate; full client build and device acceptance outstanding

## Purpose and access

The Web Workspace and Flutter App use the same club-accounting API. They do not calculate a second set of prices, ownership units, investment gains or account balances. Server-side membership, roles, book currency, source evidence, period locks and independent approval remain authoritative.

On Web, choose **Member capital, investments and accounting** from the Space's treasury page, or open `/spaces/{id}/accounting`. The former treasury implementation is preserved as `treasury-content.tsx` and still provides its existing cashbook and external-statement workflows.

In the App, the existing club-finance entry now offers **Member capital and investments** and **Treasury and external statements**. The earlier treasury screen is preserved in `financial_space_treasury_screen.dart`. The new screen does not claim that all existing CSV bank-statement import functions have been ported to native Flutter.

## Set up an accounting book

An authorised officer selects the currency, ownership model, approved cutover date and maximum accepted valuation age. A unitised book also requires an approved initial unit price. The app does not suggest an invented price or allocate missing historical ownership.

Create a separate book for each currency. Member-capital books and ownership-unit books have different subscription/redemption rules. The server rejects conflicting attempts to create the same book with different policy terms.

The new book is a draft. Use **Approve opening accounts** to enter evidenced cash balances, member capital and units, and any existing investment positions. A genuinely empty club can have empty opening arrays. Positive existing assets require corresponding explicit economic ownership. A different authorised officer must approve the opening instruction.

## Record activity using guided forms

The task menu comes from `/api/accounting/club-schema`. It includes opening accounts, contributions, redemptions, result allocations, ownership transfers, calls and monthly plans, investment acquisition/valuation/disposal/splits, ordinary cash and journal entries, reversals, distribution declarations/payments, account creation, treasury linkage and period closure.

Choose a task, confirm the displayed currency, enter its business date and complete the labelled fields. Nested lists use **Add row** and **Remove row**, not free-form JSON. Removing one row must not change the remaining values. Unknown future schema types are rejected rather than guessed.

Enter whole-number currency minor units without commas. Ownership quantities use the API's micro-unit scale. The server determines exact allocation, rounding, net asset values and other financial outcomes.

Where a matching movement already exists in the cashbook, select its original transaction identifier to classify it exactly once. Do not record another cash movement merely to make accounting agree with a statement. Evidence references identify the genuine supporting source.

## Submission, review and decision

Submission creates a **pending instruction**, not an approved accounting event and not an executed bank/mobile-money payment. Its stable request key and submitted payload hash identify one instruction.

The maker may inspect, simulate or cancel their pending instruction with a reason. They cannot approve it. A separate authorised checker reads the exact members, amounts, currency, source evidence and business date, may simulate the current result, and must acknowledge review before approving the exact hash. Rejection requires a reason.

Simulation is rolled back by the API. Simulated record identifiers are not committed records. Approval re-evaluates current records, membership, source state and period restrictions; a preview is not a price lock or bypass of financial checks.

## Interrupted requests

On the App, an attempted instruction is stored through the existing secure-storage dependency under the current user, Space and book. Re-entering the workflow resumes the same frozen request. Tokens are never embedded in that request. A result with known validation failure can be corrected while retaining its key; a timeout is not treated as proof that nothing happened.

On Web, the open page retains and locks the original request during an uncertain result. Retry the unchanged instruction or inspect the queue. **Reload-safe Web recovery is not yet implemented in this candidate.** Do not close the page or create a fresh instruction to resolve an ambiguous result. This remains a release requirement rather than a claim of offline completeness.

## Reports and frozen statements

Members default to their own records. Wider club records and operational tasks require an authorised officer. The UI cannot broaden access by sending another person's identifier; the API rechecks it.

Select a reporting period to read capital positions and movements, contribution calls and distributions. Officers can read the trial balance, financial position, income, cash-flow and investment reports, journal history and integrity findings. Unknown or incomplete evidence remains visibly different from zero.

Issuing a statement freezes its contents and integrity reference. Web provides authenticated CSV and printable HTML downloads without exposing the bearer token in a URL or browser payload. The App can issue and read the statement in-app; native file export and print/share are not included in this candidate.

An OpFin club statement is not a bank-issued statement, independent asset valuation, custody certificate or proof that a payout was sent. Check the separate treasury matching/confirmation evidence where external reconciliation is required.

## Training and UAT exercises

Use synthetic members and at least two authorised officers. Complete an empty opening; add a contribution; inspect the pending instruction; verify self-approval is absent; approve as the checker; inspect resulting member ownership and balanced records. Repeat with an investment, valuation, distribution and partial redemption.

Remove the first of two form rows and confirm the second row retains its displayed and submitted value. Try fractional money, an invalid date, an unsupported field, another book/Space and a removed officer. All relevant server denials must remain visible.

Interrupt submission after the API receives it. Resume the exact same key and verify one instruction only. On App, close and reopen the workflow and test secure recovery for the correct user and Space. Test statement retries separately. Test larger text, screen readers, touch targets, narrow screens and keyboard-only use. These exercises have not been certified by the standalone input tests.

## Developer and release notes

Web server actions and the App gateway each allow only the explicit supported accounting actions. Provider credentials are not client inputs. The existing API-origin convention already contains `/api`; do not append it twice. Redirects are rejected on authenticated requests.

The form schema assists users; backend validation is not replaced. Run the complete Next.js and Flutter checks, not just the standalone TypeScript contract harness. The latter validates a pure module, not React rendering, Next routing or a release build.

Remaining client acceptance includes full dependency-aware builds, App/device/accessibility checks, Web reload recovery, native export and former-member own-history navigation. Parent backend test results belong to its own revision. This guide is not a complete financial-launch certificate.
