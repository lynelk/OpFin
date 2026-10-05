# Financial Change Governance

Status: Controlled internal mandatory control  
Updated: 5 October 2026  
Language: English (United Kingdom)

Financial-control code is treated as higher-risk than ordinary product code.

## Sensitive change classes

The independent review gate applies to pricing, affordability and repayment mathematics; money movement and provider finality; ledger posting and account mappings; reconciliation; default interest; early settlement; fee recognition; revenue; subscriptions; partner shares; tax; savings/protection settlement and reversals; financial migrations; and regulatory financial reporting.

## Required path

1. Changes are made on a branch, not as the intended workflow directly on `main`.
2. CI identifies whether financial-control-sensitive files changed.
3. A pull request must have at least one `APPROVED` review from a GitHub user other than the PR author.
4. API, security, documentation and release gates must also pass.
5. After deployment, the financial-integrity audit and provider reconciliation controls remain required.

The CI gate also checks commits associated with `main`. Financial-control changes that cannot be associated with a reviewed pull request fail the release gate.

## Temporary pause (from 5 October 2026)

The repository owner has paused step 3 until further notice. The repository has one maintainer, and GitHub does not allow an author to approve their own pull request, so the check could never pass. The CI job still lists every changed financial-control file and records a "Financial-control review paused" warning on the run, but it no longer fails.

During the pause the owner is accountable for each financial-control change. Changes must still reach `main` through a pull request with the other gates green. To end the pause, delete the marked block in the `financial-control-review` job of `.github/workflows/ci.yml` and remove this section.

## Branch protection

`main` should be protected and require the canonical release gate plus the financial-control review check. The connected GitHub App used for this implementation has no repository-administration mutation permission, so the branch/ruleset setting itself must be applied by an authorised repository administrator.

## Principle

No financial change is accepted merely because it compiles or balances. It must preserve the expected economic event, external settlement evidence, immutable accounting and independent review.
