# OpFin publication standard

Status: Canonical documentation control  
Effective: 23 September 2026  
Language: English (United Kingdom)

## Purpose

This standard defines when an OpFin document is ready to publish, share externally or use as a controlled internal reference.

A document is publication-ready only when its audience, authority, evidence boundary and unresolved gates are clear. Attractive formatting cannot repair an unsupported claim. Humanity has tried.

## Publication classes

### Public
May be shared externally without additional editorial work, subject to normal legal/compliance approval where the subject matter requires it.

Examples: product overview, user guidance, public account-deletion instructions and approved store listing copy.

### Controlled external
May be shared with a defined external audience such as an integration partner, programme partner, regulator, auditor or implementation partner. It may contain technical detail that is inappropriate for general publication.

Examples: API references, partner reporting standards, programme frameworks and regulatory-control mappings.

### Controlled internal
Operational, engineering, release, security or UAT material. It is publication-quality as an internal controlled document but is not intended for unrestricted public distribution.

Examples: operational runbooks, release checklists, reviewer-credential templates and implementation backlogs.

### Historical evidence
Preserved for audit, migration or decision history. It must retain its date/context and must not be presented as current policy or product behaviour.

## Required publication metadata

Current controlled documents should make the following clear near the top of the document where appropriate:

- title;
- status or document class;
- last-reviewed/effective date;
- intended audience when not obvious;
- language where externally published prose is material;
- evidence or activation caveat where capabilities depend on external providers, licences, agreements or certification.

## Editorial standard

Use British English for prose. Preserve exact API fields, source identifiers, code, route names, configuration keys and third-party product names even where their spelling differs.

Use:

- short, descriptive headings;
- sentence case unless a proper name requires otherwise;
- consistent product naming: **OpFin**;
- **Financial Space** / **Financial Spaces** where referring to the canonical domain concept;
- explicit dates rather than vague relative wording in controlled evidence;
- plain language before technical terminology in user-facing documents.

Avoid:

- unsupported superlatives;
- claims that source code proves regulatory approval, provider activation or production certification;
- descriptions of pending financial events as completed;
- invented provider credentials, legal details, APRs, pricing or reviewer accounts;
- stale screenshots or illustrative images presented as production evidence;
- unresolved editorial markers such as TODO, TBD, FIXME or dummy contact details in documents classified Public.

## Evidence language

Use these distinctions consistently:

- **implemented**: supported by current source;
- **deployed**: the stated source/version has deployment evidence;
- **activated**: required provider/configuration/legal gates are satisfied;
- **verified/certified**: the applicable acceptance evidence exists;
- **publication-ready**: the document itself is editorially complete for its stated class.

These states are not interchangeable.

## Links and references

Relative repository links must resolve. Public URLs must be verified before publication where tooling/access permits. If a URL cannot be verified, label it as a publication gate rather than asserting availability.

## Sensitive information

Never publish:

- PINs, OTPs or reviewer passwords;
- API secrets, private keys or tokens;
- real customer KYC images/data;
- private provider credentials;
- confidential commercial terms unless explicitly approved for the audience.

Release templates may state where a sensitive value must be entered, but the value itself stays outside source control.

## Final publication gate

Before publication:

1. confirm the document class and audience;
2. run `make publication-check`;
3. reconcile product/API facts against current `main`;
4. confirm legal/regulatory/provider facts that cannot be proven by source;
5. verify external URLs where possible;
6. confirm no sensitive information is embedded;
7. record the publication date/version where required.

See `docs/PUBLICATION_REGISTER.md` for the repository classification.
