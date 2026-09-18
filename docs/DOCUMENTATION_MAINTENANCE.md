# Maintaining OpFin documentation

Review date: 18 September 2026. Audience: engineering, product, support, trainers and release owners.

## One source, several audiences

The repository on `main` is the shared editing source. Use [the documentation hub](README.md) to navigate it. Customer manuals and operational guides reuse the same task definitions; they must not become independent copies of business rules.

Keep four layers distinct: strategic concepts describe intended direction; product and control documents describe approved requirements; implementation references describe observed code; release records describe evidence for one candidate and environment. When code and an approved rule disagree, record a defect. Do not rewrite the rule to legitimise the defect.

Use British English, plain explanations before technical terminology, exact application labels and synthetic examples. Expand abbreviations on first use. Preserve older audit evidence with its date and context.

## What is automated

| Control | Behaviour | Important limit |
| --- | --- | --- |
| Change-impact check | Maps changed runtime, request, middleware, configuration, dependency and deployment paths to relevant surviving documentation | A reviewer must still verify meaning and completeness |
| Local-link check | Reports missing local Markdown file targets; a PR fails on defects in changed current documents | External URLs, heading fragments, complex Markdown and screen correctness are not certified |
| Route-reference generation | Reads Laravel's registered routes; lists methods, URIs, handlers and middleware | It is not a complete OpenAPI schema or an authorisation audit |
| Endpoint-table comparison | Rejects curated endpoint entries that are absent from the route export; normalises parameter names and query strings | Environment-specific and feature-gated registration still needs release checks |
| Narrative coverage report | Lists registered operations missing from the two curated endpoint tables | A generated row does not supply validation rules, error contracts or tested examples |
| Browser search | Rebuilds from tracked Markdown at the candidate commit | No uncommitted private notes, environment files or provider credentials are deliberately indexed |
| Provenance | Records the commit, tracked-worktree state and API registration environment | A source commit is not evidence of deployment |

Deleted or empty documents do not satisfy change-impact checks. Audit/demo/migration/checkpoint/release records cannot substitute for current instructions.

## Local commands

```bash
make docs-search QUERY="repayment"
python3 scripts/search-docs.py "credit reporting" --api
python3 scripts/search-docs.py "migration" --history
make docs-test
make docs-check BASE=origin/main
make docs-build
make docs-serve
```

The portal is `.build/docs/index.html`. It is a read-only source viewer with browser search, historical filtering and pinned GitHub source links. Open the HTML file directly, or use the loopback-only server. It does not execute API requests, need a token, load external assets or publish itself.

The inventory lists every tracked Markdown document included by the tool, with a content hash. “Current reference” means it is outside recognised historical paths, not that every statement has been independently certified. Review unclassified or contradictory content before using it in a manual.

## Generate the API reference

Use a disposable local/test environment with dependencies installed and no production credentials. From repository root:

```bash
mkdir -p .build/api
(cd apps/api && APP_ENV=testing php artisan route:list --json) > .build/api/registered-routes.json
python3 scripts/docs_toolkit.py export-api \
  --routes .build/api/registered-routes.json --environment testing
python3 scripts/docs_toolkit.py build --api-dir .build/api
```

The export deliberately omits demo routes and implicit `HEAD` entries. Middleware is shown as registered, without guessing a public/private classification. Review controller-level ownership checks, role policies, callbacks and capability gates separately.

Generated files remain in `.build/`, not in a second hand-maintained contract tree. The workflow builds them again for every candidate. A portal refuses an API export from a different commit or tracked-worktree state. Do not reuse a testing export as a production route certificate.

## CI and release workflow

The `Documentation quality` workflow runs on pull requests, pushes to `main`, manual dispatch and a daily schedule. It uses read-only repository permissions, no production secrets and a disposable testing configuration. On pull requests it compares the exact base and candidate commits. For a push it compares the event's previous commit with the new head, including multi-commit pushes.

The workflow exports the route reference, reports coverage gaps, builds the portal and retains a commit-labelled artefact. Check both the job result and coverage report. Existing unrelated link defects remain visible in PR output; full scheduled/manual audits report them as failures until repaired.

Repository administrators must make `documentation` a required check to prevent bypass. That protection must be verified separately. Daily checks detect problems; they do not edit business explanations or promise automatic accuracy.

## Training and user-guide lifecycle

Use [training paths](manuals/TRAINING_PATHS.md) and [the task template](manuals/TASK_GUIDE_TEMPLATE.md). Each published guide records its audience, source commit, release/environment, feature availability, reviewer and test evidence. Keep `LIVE`, `LIMITED PILOT`, `PLANNED` and `UNVERIFIED` separate. Only use `LIVE` when release evidence supports it for the stated audience and channel.

For every affected task, verify the happy path, permission failure, validation failure, interrupted connection, pending provider state and recovery/escalation path. Screenshots must come from the stated build with synthetic data. Do not generate screenshots and describe them as production evidence.
