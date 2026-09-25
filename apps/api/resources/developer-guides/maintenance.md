# Keep contracts aligned with the implementation

Audience: maintainers, reviewers and release owners.

## Source-linked, not a detached manual

The live catalogue reads the application's registered routes. The published guides and reviewed schemas are checked-in files. A running deployment returns fingerprints for its source and documentation; it does not assume that remote main and production are identical.

New routes appear as registration-only until reviewed contracts are supplied. They are deliberately absent from SDK-oriented OpenAPI. This makes missing work visible instead of silently generating plausible but invented request schemas.

## Export and check

From `apps/api`:

```bash
php artisan api:catalogue
php artisan api:catalogue --check
php artisan api:catalogue --check --require-complete
php artisan api:catalogue --check --baseline=/approved/prior/catalogue-snapshot.json
```

The default export location is `storage/app/developer-catalogue`. Outputs include reviewed OpenAPI, a route snapshot, coverage, guide text and a change report. Exports contain source metadata, not customer or provider secrets.

`--require-complete` fails while any registered operation lacks a reviewed contract. That failure is useful evidence, not a reason to mark incomplete entries documented. Without a baseline, the command explicitly states that compatibility comparison was not run.

The baseline comparison identifies added, removed and changed operations and controller/middleware drift without a corresponding contract update. Domain-service changes also require review even when the route's shape is unchanged. Automated checks cannot prove the truth of every sentence or close an unresolved financial defect.

## Update in the same change

A route/validation/response change updates its reviewed schema and examples. A role, consent, Space or error change updates the safety explanation and negative tests. A provider-state or financial change updates idempotency, confirmation, finality and reconciliation guidance. A customer workflow change updates the training task and UAT evidence.

Use root and component README links to these canonical guides rather than maintaining another copied contract. Preserve historical audit/release evidence with its original date. A later defect does not authorise rewriting the original product requirement.

## Release gate

Run the existing API test, format, dependency-audit and documentation/publication checks, plus affected client contracts. Review coverage and baseline differences. Run `--require-complete` before claiming the whole API specification complete, not merely before publishing the discovery interface.

GitHub Actions remains deferred under the owner's instruction. This interface works without Actions and adds no scheduled hosted workload. Actual provider agreements, approved NIN reuse intervals, security remediation and production evidence remain explicit gates.
