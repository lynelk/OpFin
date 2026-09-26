# Club client recovery, history and native export

Status: Implementation candidate; release gates remain mandatory  
Updated: 26 September 2026  
Language: English (United Kingdom)

## What changed

Web and Flutter now use a server-persisted request before submitting an accounting instruction or issuing a statement. Reloading, changing devices or losing an HTTP response does not require a new financial identity. The envelope is encrypted at rest using the application's existing encryption configuration. No bearer token is stored in it.

This is not a new approval mechanism or payment route. Submission creates a pending instruction, or issues an authorised statement. Acknowledgement only releases the recovery slot after the result has been read. It does not approve accounting or execute a provider.

## Authenticated API

All routes require Sanctum and the existing API throttle. IDs and references are bound to the current user and exact club book. Instruction recovery requires current maker authority. Statement recovery uses the existing member/officer report authorisation, including retained own-member history.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/api/accounting/saved-requests` | Paginated metadata for the current user's unresolved requests; optional `space_id` and `page` |
| POST | `/api/financial-spaces/{space}/accounting/books/{book}/client-requests/prepare/{purpose}` | Save an `instruction` or `statement` envelope without performing its domain operation |
| POST | Same prefix, `/inspect` | Read the exact saved envelope after current authorisation |
| POST | Same prefix, `/submit` | Resume the original domain operation or return its recorded result |
| POST | Same prefix, `/acknowledge` | Acknowledge a recorded result, without approving it |
| POST | Same prefix, `/cancel` | Cancel a genuinely unsubmitted saved request |

`prepare` takes the existing instruction or statement body, including its original `idempotency_key`. Other POST operations take `reference` and `content_hash` from the saved request. The list does not include full envelopes: clients retrieve one explicitly, keeping responses bounded and avoiding bulk disclosure.

One active recovery slot exists per user, book and purpose. A different request cannot overwrite it. Matching replay retains the original identity. Cancellation checks the original domain tables as well, so a request submitted through an older client cannot be cancelled as if it never reached the server.

Once a request is saved, an application validation failure does not authorise changing that saved meaning. The clients keep it frozen; cancel an unsubmitted request explicitly, then start a new reviewed request. A lost response is not proof of non-submission.

## Existing routes retained

The original instruction, approval, report, statement and export routes remain available. The new recovery routes are additive. `GET /api/accounting/my-club-books` remains the source for retained member positions. Former membership does not authorise another member's history or whole-club reports.

The guided schema now exposes the existing optional `treasury_transaction_id` for ordinary reversals. This permits an actual opposite cashbook source to be selected where the server requires evidence for reversing externally derived cash.

## Web workflow

Open **Saved requests** on the accounting page, refresh the list and inspect the saved request. Resume it, read the recorded result, then acknowledge it before starting another request of the same purpose. A cancelled unsubmitted request requires reopening the form for a new key.

`/club-history` is linked from My financial spaces and the accounting page. It reads only the signed-in member's retained records, even after leaving a club. Statement export still uses the server-side authenticated proxy, never a bearer token in a URL.

## Flutter workflow

The club workspace footer exposes **Saved requests** and **Records and exports**. My spaces also exposes retained history independently of current membership. The original treasury and club workspace implementations remain intact behind navigation wrappers.

The native export bridge supports CSV/HTML save, CSV share and HTML print through the operating system. It receives the authenticated statement bytes, not API credentials. Formats, names and payload size are validated on both sides of the bridge. Android sharing grants temporary read-only access to a restricted cache directory. iOS temporary files use complete file protection.

Opening a share or print dialogue is reported as `presented`, not as confirmed delivery or printing. Cancellation is distinct. A file-save completion is reported only after the destination write succeeds. Export does not change club financial state.

## Release verification

Run `node scripts/test-club-recovery-actions.cjs` in addition to the existing Web checks. Its mocked transport checks do not replace a Next.js build or browser tests. Run the new `ClubClientRequestsTest` with the complete Laravel suite on SQLite and PostgreSQL, including migration upgrades and repeated clean installation. Run Flutter analysis, existing/new widget tests and Android/iOS release compilation.

The native policy can be tested independently using the Kotlin test in `scripts/tests/StatementExportPolicyTest.kt`. Test real device document pickers, print cancellation, iPad presentation, large text, screen readers, revoked sessions, switching accounts, and interrupted submissions. Do not mark these device or framework gates passed from pure policy or parsing checks.
