# Club accounting: Web and App workflow guide

Version: 26 September 2026, recovery and native-export revision  
Language: English (United Kingdom)  
Status: Implementation candidate; full client build and device acceptance outstanding

## Purpose and access

Web Workspace and Flutter App use the same club-accounting API. They do not calculate a second set of prices, ownership units, investment gains or account balances. Server-side membership, roles, currency, source evidence, period locks and independent approval remain authoritative.

On Web, choose **Member capital, investments and accounting** from the Space's treasury page, or open `/spaces/{id}/accounting`. The previous treasury implementation remains in `treasury-content.tsx` and retains its cashbook and external-statement functions.

In the App, the club-finance entry offers **Member capital and investments** and **Treasury and external statements**. The previous treasury screen is preserved in `financial_space_treasury_screen.dart`. This does not imply that the Web CSV import/reconciliation tools have been ported to native Flutter.

## Set up an accounting book

An authorised officer selects the currency, ownership model, approved cutover date and maximum accepted valuation age. A unitised book also requires an approved initial unit price. OpFin does not invent a price or allocate missing historical ownership.

Use a separate book for each currency. Member-capital books and ownership-unit books have different subscription/redemption rules. Conflicting attempts to create the same book with different policy terms are rejected.

The new book is a draft. Use **Approve opening accounts** to enter evidenced cash balances, member capital and units, and existing investment positions. A genuinely empty club can have empty opening arrays. Positive existing assets require corresponding explicit ownership. A different authorised officer must approve the opening instruction.

## Record activity using guided forms

The task menu comes from `/api/accounting/club-schema`: opening accounts, contributions, redemptions, result allocations, ownership transfers, calls and monthly plans, acquisition/valuation/disposal/splits, ordinary cash and journal entries, reversals, distributions, ordinary-account creation, treasury linkage and period closure.

Choose a task, confirm the currency, enter its business date and complete the labelled fields. Nested lists use **Add row** and **Remove row**, not free-form JSON. Removing one row must preserve the remaining displayed and submitted values. Unsupported future field types are rejected rather than guessed.

Enter whole-number currency minor units without commas. Ownership uses the API's micro-unit scale. The server calculates allocations, rounding and net asset values.

Where a matching movement already exists in the cashbook, use its transaction identifier to classify it once. Reversing externally derived cash may require the **actual matching opposite cashbook entry**. An evidence reference and an arithmetic match are not issuer-confirmed statement authenticity.

## Submission, review and decision

Submission creates a pending instruction, not an approved event or an executed bank/mobile-money payment. Its request key and payload hash identify one instruction.

The maker may inspect, simulate or cancel their pending instruction with a reason. They cannot approve it. A different authorised checker reads the members, amounts, currency, source and date, optionally simulates the current result, acknowledges review and approves the exact hash. Rejection requires a reason.

Simulation is rolled back. Its identifiers are not committed records. Approval re-evaluates current membership, source state and period restrictions; a preview does not bypass financial checks.

## Interrupted requests and acknowledgement

Web and App first save the exact request on the server, encrypted and scoped to the current user, book and purpose. This closes the earlier Web reload-recovery gap. The App also retains its existing secure-storage recovery information; neither method stores a bearer token in the financial request.

Open **Saved requests** after a timeout, reload or device change. Refresh the list, inspect the original request and choose **Resume or read original result**. This returns the original instruction or statement rather than creating another one. Read it and choose **I have read this result** to release its recovery slot before another request of that purpose.

Acknowledgement is not accounting approval or payment execution. An unsubmitted request can be cancelled explicitly. A request already recorded in the domain cannot be cancelled as if unsent; use the ordinary instruction cancellation/decision workflow where allowed. After cancelling a saved unsubmitted request, reopen the form to obtain a new key.

A current role is rechecked when recovering a club instruction. Revoked access does not permit reading a previously saved whole-club payload.

## Reports, retained history and frozen statements

Members default to their own records. Officers may read wider club records when their current role permits it. Supplying another member's ID does not widen authorisation.

**My club capital history**, linked from Web My financial spaces and App My spaces, remains reachable after leaving a club. It uses retained member positions rather than requiring current membership. It does not expose another member's records or full-club accounts to former members.

Select a reporting period for positions, movements, calls and distributions. Officers can also inspect trial balance, financial position, income, cash flow, investments, journal history and integrity findings. Unknown values remain distinct from zero.

Issuing a statement freezes its contents and integrity reference. It uses the same saved-request recovery flow. A statement is not a bank-issued document, independent valuation, custody certificate or proof of payout. Check the separate treasury matching/confirmation evidence when external reconciliation is required.

## Native export and printing

Web exports authenticated CSV and printable HTML without placing a bearer token in a URL. In the App, open **Records and exports**, choose a book and issued statement, then select **Save CSV**, **Share CSV**, **Save statement**, or **Print / save PDF**.

The operating system controls the destination, recipient and available printers. Files contain financial information; check the destination carefully. Cancelled, saved and dialogue-opened results are distinct. Opening a share or print dialogue is not confirmation of delivery or printing.

The device receives statement bytes, not API credentials. Export accepts only supported formats and bounded document sizes. It does not change financial state. Unavailable print services or failed destination writes must be reported rather than presented as success.

## Training and UAT exercises

Use synthetic members and two officers. Complete an empty opening; add a contribution; inspect its pending instruction; verify self-approval is absent; approve as the checker; inspect balanced records. Repeat for an investment, valuation, distribution and partial redemption.

Remove the first of two form rows and confirm the second retains its values. Try fractional money, invalid dates, unsupported fields, another Space and a removed officer. Server denials must remain visible.

Interrupt a request after server preparation and after domain submission. Reload Web or restart App, resume the same request and verify one domain record. Acknowledge it, then start a different request. Verify cancellation is available only while truly unsubmitted. Repeat for a frozen statement and for a different signed-in user.

Leave a club and navigate through retained history: only the former member's own records should be available. Exercise CSV save/share, HTML/PDF printing, cancellation and unavailable destination services. Check narrow screens, text scaling, screen readers, touch targets and keyboard focus. These exercises are not certified by isolated parsing or policy tests.

## Developer and release notes

The API remains the sole financial authority. The existing API-origin convention already includes `/api`; do not append it twice. Redirects are rejected on authenticated requests. Full source envelopes are retrieved one at a time from recovery rather than included in bulk metadata listings.

Run the complete API, Web and Flutter gates, migration checks and independent review on the exact candidate. Targeted transport, policy and parsing checks supplement, not replace, full application and device acceptance. Read [the recovery API contract](../../apps/api/docs/api/CLUB_CLIENT_RECOVERY.md) and [current implementation evidence](../operations/CLUB_RECOVERY_EXPORT_IMPLEMENTATION_2026-09-26.md).
