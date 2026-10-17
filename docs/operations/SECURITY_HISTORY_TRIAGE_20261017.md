# OpFin history and secret-scanning triage

Date: 17 October 2026. Scope: `lynelk/OpFin` full bare Git mirror, reachable branches/tags visible to authorised GitHub token.

- Used `scripts/audit-reachable-secret-history.py` against a **full mirror** (not a partial clone). No secret values were printed or committed.
- 4,884 named Git blobs inspected; no oversize blobs skipped.
- 35 historical sensitive-artifact objects identified (e.g. historical archives or restricted secret-bearing file extensions); **not** a count of confirmed live exposed credentials.
- The limited regular-expression detector did not report private-key/token signatures in this run. It cannot exclude credentials embedded in archive contents, different credential formats, external clones or forks.
- Repository current-tree security scan remains separate from historical credential validity.
- GitHub repository remains **public** (visibility not changed without product-owner decision).
- GitHub **Secret Scanning enabled** on 17 October; provider API initially returned 0 open/closed alerts, with ingestion and coverage caveats. Push protection was not enabled automatically, to avoid changing owner-directed release policy.
- Current GitHub `main` branch protection was **not active** at the check; an owner decision is needed on PR gating and release-policy precedence.

## Required private incident closure

1. Inventory historical artifact content **only** in an approved restricted security environment; assign actual credential owner and provider.
2. Establish validity, access history, reuse and dates. Rotate/revoke **only those actually exposed or reused**, coordinate downstream service updates and downtime safely.
3. Review exposure period provider activity and record redacted evidence in a private incident location, not the public repository.
4. Determine whether rewriting history is justified. Require backups and collaborator/fork coordination, not a forced main rewrite.
5. Confirm weekly backup and independent encrypted offsite destination, isolated restore evidence, availability of GitHub rulesets, and appropriate source visibility.
6. Obtain recorded security-owner acceptance. **This document is triage evidence, not incident closure.**

Owner-directed non-destructive code testing proceeds independently of these unresolved operational actions.
