# Contributing to OpFin

Review date: 18 September 2026. Audience: contributors, reviewers and release owners.

## Start safely

Use [the developer guide](docs/DEVELOPER_START_HERE.md), [engineering rules](AGENTS.md) and [security requirements](SECURITY.md). Work from an up-to-date `main` branch. Never copy production databases, customer identity evidence, provider keys or real customer tokens into development or documentation examples.

```bash
git switch main
git pull --ff-only
git switch -c feature/descriptive-change
make help
```

## A change includes its documentation

Update the implementation, tests and relevant documentation in the same pull request. A generic README edit does not replace an API contract update. An archived audit note does not satisfy a current-documentation requirement.

| Change | Required documentation review |
| --- | --- |
| Registered API routes | [Current endpoints](apps/api/docs/api/current-endpoints.md); generated route-reference verification |
| Request fields, validation, responses or authorisation | Current endpoints, [client contract](apps/api/docs/api/frontend-backend-contract.md), [integrator guide](apps/api/docs/api/INTEGRATOR_GUIDE.md) and affected tests |
| Financial or regulatory behaviour | Relevant API/architecture document, operational runbook and UAT evidence; launch/compliance guide where applicable |
| Customer or admin workflow | Component README, journey description and affected training task |
| Dependencies, environment variables or local commands | Component README or developer setup guide, including compatibility and verification notes |
| Deployment, security or CI | Security, contributor, maintenance or operational guidance |

For an internal-only change, explain why the external contract is unchanged in the relevant current document and PR. There is no blanket “skip documentation” switch. Reviewers assess whether the explanation is substantive.

## Verify before review

Stage new documentation so the tracked-file index includes it. Run the commands on the candidate branch, not on an unrelated checkout.

```bash
make docs-test
make docs-check BASE=origin/main
make docs-build
# Run affected product gates as well:
make api-test
# make web-test
# make client-test
```

[Documentation maintenance](docs/DOCUMENTATION_MAINTENANCE.md) explains API exports, historical sources and the limits of automated checks. A passing documentation job does not prove that prose is accurate, an API is safe, or production is ready.

## Review and merge

Record the candidate commit, test results, affected audiences, changed contracts and any unresolved risks. Do not merge a failing release candidate or bypass required checks. Preserve other contributors' work; do not force-push `main`.

Require the `documentation` check from the documentation workflow alongside existing release/security checks in branch protection or repository rulesets. Configuration of those repository settings is a separate administrative control; committing a workflow does not enable it automatically.

Production publication needs the normal release process. A merged source change, a generated documentation artefact and a deployed service are three different states.
