# Documentation maintenance review: 18 September 2026

## Review basis

Initial inspected `main` commit: `3d0038347161e94e56a80bc34ed82cf6ffad7524`. This record concerns documentation and CI tooling. It does not certify a deployed release, provider activation, store publication or a complete semantic review of every historical document.

## Findings and corrections

The repository already contained a documentation hub, training foundation, search scripts and a basic change-impact check. This change extends that work rather than claiming it was absent.

The API index contained an incorrect root README path. Several navigation references were code-formatted paths rather than usable links. The developer setup did not clearly separate local configuration, missing SMS configuration, source versions and deployment readiness. API discovery had no offline snapshot provenance or complete generated registration inventory.

The previous documentation check missed changes under request/middleware/model/configuration/shared-contract paths, accepted overly broad documentation locations, and could allow a deleted document's changed filename to satisfy a rule. Without a base ref it performed no change comparison while reporting a generic pass.

The inspected `ci.yml` also contained repeated job blocks and a detached shell fragment following a release-gate block. The repaired workflow retains one copy of each existing gate, corrects changed-PHP-file selection and adds the reusable documentation job to release prerequisites. Exact PR-base/push-before comparisons cover multi-commit pushes.

## Implemented controls

The new standard-library tooling inventories tracked Markdown, builds an offline searchable source viewer, distinguishes historical evidence, checks local file links, exports non-demo Laravel route metadata, verifies the curated endpoint tables and exposes narrative coverage gaps. The export records commit and environment; it does not claim complete request/response schemas.

Current guides now include contributor workflow, API integration, safer local setup, maintenance ownership, plain-language terminology, role-based training paths and a reusable task/UAT template. A PR template prompts explicit contract, documentation and verification evidence.

## Verification and remaining acceptance

Local regression tests cover discovery exclusions, historical filtering, link checking, injection safety, route validation, duplicate detection, source provenance and documentation-impact rules. Workflow YAML and embedded shell syntax are checked locally before submission.

The actual GitHub run and generated coverage artefact remain the authority for candidate-specific route counts and integration with the full repository. Do not infer a green hosted run from local tests. Any existing link or narrative gaps reported by those checks require review; they are not silently labelled complete.

Branch protection/rulesets must require the documentation and release gates. A workflow file alone cannot prevent an administrator from bypassing checks. Full external-link checking, screenshots, real-device acceptance and exhaustive request/response schemas are outside the checks implemented here.
