# OpFin public website

Status: candidate canonical public-marketing source  
Established: 2 October 2026  
Language: English (United Kingdom)

## Purpose

This directory is the repository-owned candidate source for the single public OpFin marketing website described in the integrated delivery plan. It is deliberately separate from authenticated product and workspace implementation.

The public site should answer five questions quickly:

1. What is OpFin?
2. Who is it for?
3. What financial jobs can it help with?
4. How does a new customer start?
5. How does an existing or authorised user sign in?

It must not expose internal operations as public navigation, present planned/provider-gated capabilities as live, or create a second authentication model.

## Access model

- New customers start phone-first in the OpFin App.
- Existing customers and authorised workspace users use one Web sign-in.
- Employer, partner and OpFin Operations routing happens after authentication and role checks.
- Separate public “Borrower Dashboard”, “Admin Portal”, “Support Portal” and similar entry points are intentionally not shown.
- Privacy and account-deletion links remain available in the footer at lower visual prominence.

## External links

The candidate currently uses repository-documented setup addresses:

- Web sign-in: https://opfin-web-production.up.railway.app/login
- Privacy policy: https://opfin-production.up.railway.app/privacy-policy
- Account deletion: https://opfin-web-production.up.railway.app/account/delete

These addresses are repository-supported setup references, not evidence of current production health or custom-domain cutover. Before publishing, run authenticated/live verification and replace them with verified custom-domain routes where approved.

## Validation

Run:

```bash
node sites/opfin-public/check-links.mjs
```

The checker rejects placeholder links, missing same-page targets, unexpected external hosts and any visible admin/operations portal link.

Live HTTP status checks are a separate publication gate because this repository validation must not mistake source correctness for production availability.

## Publication boundary

Do not publish this candidate merely because it exists in source. SITE-001 through SITE-008 still require host/source parity, version/rollback evidence, link checks, accessibility review, security/privacy review, performance checks and observed live verification before cutover.
