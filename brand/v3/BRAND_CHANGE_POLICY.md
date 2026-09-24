# OpFin Brand Change Policy

Status: Controlled policy  
Candidate version: 3.0.0-rc.1  
Updated: 24 September 2026

## Purpose

Prevent accidental brand drift across OpFin product, Web, mobile, app stores, communications, partner materials and generated assets.

## Canonical sources

1. `brand/opfin.tokens.json` for machine-readable tokens and version.
2. `brand/v3/OPFIN_BRAND_SYSTEM_V3.md` for usage rules.
3. `brand/v3/assets/` for vector masters.
4. Generated platform assets recorded in `brand/asset-manifest.json`.

If an exported PNG conflicts with the approved vector/token source, the vector/token source wins and the export must be regenerated.

## Change classes

### A. Patch

Examples: correcting a template typo, adding an approved export size, clarifying documentation without changing meaning.

Requires:
- normal pull request;
- brand drift/security checks;
- visual review of affected output.

### B. Minor system extension

Examples: new chart pattern, new communications template, additional approved lock-up, new channel specification.

Requires:
- documented rationale;
- accessibility review;
- product/brand review;
- updated toolkit and examples;
- pull request with affected surfaces listed.

### C. Major identity change

Examples: logo geometry, core palette, typography family, tagline, core promise, Progress Path, Financial Compass or Next Step visual grammar.

Requires:
- explicit executive approval;
- brand/comms/design owner approval;
- legal/trademark review where relevant;
- accessibility review;
- migration plan across all active surfaces;
- version-major change;
- no direct-to-main replacement.

## Protected rules

- Do not silently change `brand/opfin.tokens.json`.
- Do not manually replace generated icons without updating provenance.
- Do not introduce a new brand colour simply to fix one screen.
- Do not make marketing claims broader than activated product capability.
- Do not use illustrations as evidence of production functionality.
- Do not mark a candidate as frozen when required external evidence is incomplete.

## Engineering enforcement

Pull requests changing brand-controlled files must run:
- `python3 scripts/sync-brand.py --check`;
- `python3 scripts/verify-security-controls.py`;
- applicable Web and mobile tests/builds;
- store-asset generation checks when distribution artwork changes.

A visual review is still required. Automated tests are very good at detecting hex codes and surprisingly poor at noticing that a line has been drawn through the word “clearer”.

## Freeze

After all v3 release gates pass:
- change `brand/opfin.tokens.json` version to `3.0.0`;
- change the v3 documents from Release candidate to Frozen;
- record date, source commit and approvers;
- tag the release;
- require this policy for every subsequent change.
