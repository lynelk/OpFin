# OpFin Brand System distribution package

Version: 3.0.0-rc.1 (release candidate, not frozen)  
Updated: 4 October 2026  
Language: English (United Kingdom)

This is the controlled way to hand the brand to designers, agencies, partners and store managers. Build the package from committed sources, so what people receive matches what was reviewed.

## Build it

```sh
sh scripts/package-brand.sh
```

The script writes `dist/brand/opfin-brand-system-<version>.zip`, using `git archive` on the committed `HEAD`, so uncommitted edits cannot slip in. `dist/` is not tracked.

## Contents and status

| Part | Location in the package | Status |
|---|---|---|
| Tokens: colour, type and source pointers | `brand/opfin.tokens.json` | Approved v3 direction |
| Editable vector masters | `brand/v3/assets/` | The monogram, app icon, Progress Path, wordmark and lock-ups are path-only vector masters. The wordmark and lock-ups were outlined from the licensed Inter SemiBold on 8 October 2026. |
| Generated exports with manifest | `brand/v3/exports/` and `EXPORT_MANIFEST.json` | Generated 4 October 2026. Visual review against the masters is pending (release gate 4). |
| Starter templates | `brand/v3/templates/` | Starters. Build production files in the design tool from these. |
| Brand standard and change policy | `brand/v3/OPFIN_BRAND_SYSTEM_V3.md`, `BRAND_CHANGE_POLICY.md` | Current |
| Visual QA and screenshot standards | `brand/v3/VISUAL_QA_CHECKLIST.md`, `SCREENSHOT_CAPTURE_STANDARD.md` | Current; sign-off pending |
| Trademark record | `brand/v3/TRADEMARK_CLEARANCE.md` | NOT CLEARED. Do not imply registration. |
| Changelog and superseded identities | `brand/v3/CHANGELOG.md` | Current |
| Inter font and licence | `apps/web/public/brand/`, `apps/client/assets/brand/` | SIL Open Font License 1.1 |

## Rules for recipients

- Use the exports or the masters. Never redraw, trace or recolour the monogram outside the approved variants.
- Use the reverse variant only on Indigo or similarly dark backgrounds. Use mono variants only where colour printing is unavailable.
- Product claims must match what is actually live in the named channel. The one-pager's costs-and-risks panel is mandatory.
- Do not label this release candidate as "final" or "frozen", and do not suggest the name is a registered trademark.

## Ownership

- Named brand owner: [to be recorded by OpFin].
- Recipients: [to be recorded by OpFin].
- Distribution schedule: [to be recorded by OpFin].

Issue #103 tracks these until they are recorded.
