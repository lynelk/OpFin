# OpFin background deployments: code and dependency watch policy

Updated: 21 October 2026. This is an operations/build note, not a change to customer finance contracts or access rights.

## One source, three API consumers

The existing OpFin API, private worker and private scheduler all build from the canonical `lynelk/OpFin` `main` source. They share `apps/api` code and PHP/npm dependencies, but run independent service start commands and deploy only when a watched source path changes. There must be no additional database/service merely to publish dependency fixes.

* API watches `/apps/api/**`.
* Worker and scheduler watch runtime PHP, configuration, database, routes and `/apps/api/railway/**`, plus both Composer manifests/locks.
* **They must also watch `/apps/api/package.json` and `/apps/api/package-lock.json`**. Npm dependency patches may be security-critical even when PHP application files do not change. These paths were added to the corresponding `infrastructure/railway/*.json` files in PR #209 and the two existing Railway production service build watch configurations.
* This file is deliberately in the **already watched** `/apps/api/railway/**` directory so all three existing services are rebuilt at the same patched commit; no service code or provider secrets are modified.

## Acceptance for the 21 October follow-redirects patch

1. Use exact `main` source incorporating PR #208: `apps/api/package-lock.json` resolves patched `follow-redirects@1.16.1` rather than vulnerable 1.16.0.
2. Require successful OpFin Monorepo CI (API, assets, Web, Android, iOS, layout, finance and publication), security monitoring and deployment contract checks on the release candidate before merge.
3. The worker and scheduler must each show a *new* successful Railway deployment at a commit including this marker and PR #208. A status of **SKIPPED**, or an old online container, is **not** an accepted rebuild.
4. Verify API ready state, private worker and scheduler process stability and recent queue/cron execution without producing test customer money events or changing production data.
5. Preserve source-matched production secrets, queue/job idempotency, snapshot/restore controls and least-privilege service identities. Do not add new services or rewrite production history.

The release may be recorded only after the actual deployments settle successfully. Any cancelled/failed build must keep the prior healthy replica online and be investigated, not hidden by manually marking a job green.
