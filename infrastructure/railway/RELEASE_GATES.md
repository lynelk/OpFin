# Production deployment gates

The worker and scheduler now run `deployment:wait-for-schema` before processing. The command reads the migration repository, waits up to 300 seconds for all migration files present in this release, and exits unsuccessfully if migrations are missing or database checks fail. It never executes migrations itself. Only the API pre-deploy hook migrates. This prevents independent monorepo deployments from consuming work against an old schema; use expand/contract migrations for compatibility with already-running old releases.

The Railway web build and start commands run `scripts/validate-production-env.mjs`. A missing or malformed `NEXT_PUBLIC_OPFIN_API_URL` fails the deployment. Web and mobile release endpoints must use HTTPS, a public DNS hostname and `/api`. Local names, IP literals, numeric aliases, credentials, query strings and fragments are rejected. These are configuration checks, not a substitute for live API/browser smoke tests or DNS verification.

The public web domain still requires successful external HTTP evidence before announcing availability. A running private service alone is insufficient. Wait for CI remains a Railway service setting and is not silently implemented by these files. Historical credential remediation and provider certification remain separate launch gates.
