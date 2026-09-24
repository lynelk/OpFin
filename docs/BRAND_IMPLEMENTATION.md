# OpFin brand implementation

Status: Brand System v3 release-candidate implementation reference  
Version: 3.0.0-rc.1  
Updated: 24 September 2026  
Language: English (United Kingdom)

## Canonical direction

OpFin retains the established monogram and the Indigo, Apricot and Warm Ivory identity. The brand promise remains **Your next step, clearer.**

The complete v3 usage standard is `brand/v3/OPFIN_BRAND_SYSTEM_V3.md`. The machine-readable source is `brand/opfin.tokens.json`.

Core values:
- Indigo `#353B78`;
- Strong Indigo `#29305F`;
- Apricot `#F2B38D`;
- Warm Ivory `#FAF8F2`;
- Periwinkle `#E8EAF6`;
- Ink `#202436`;
- Muted `#555D72`;
- Line `#D7DAE8`;
- White `#FFFFFF`.

Semantic Success, Warning and Danger colours remain reserved for status.

## Vector identity

The v3 vector source files are under `brand/v3/assets/`:

- `opfin-symbol-master.svg` — canonical monogram;
- `opfin-wordmark.svg` — controlled wordmark artwork;
- `opfin-lockup-horizontal.svg`;
- `opfin-lockup-stacked.svg`;
- `opfin-app-icon-master.svg`;
- `opfin-progress-path.svg`;
- `opfin-feature-graphic-master.svg`.

Web renders the vector monogram directly. Mobile and store raster exports remain provenance-controlled through `brand/asset-manifest.json` and the asset-generation scripts.

Inter is self-hosted and bundled from the pinned official source recorded in `brand/opfin.tokens.json`. Applications do not rely on a runtime external font CDN.

## Product visual language

Brand System v3 introduces three repeatable product signals.

### Progress Path

A rounded directional motif derived from the monogram. Periwinkle represents context; Apricot represents the active or next route. It may frame, underline or connect, but must not cross important text.

### Financial Compass

A calm evidence-first presentation of financial position. Recorded facts, estimates and unavailable information remain distinct. Financial figures stay connected to provenance and explanation rather than being presented as decorative certainty.

### Next Step

One recommended action, one reason and one primary action. Web and mobile Home now use the pattern as a signature OpFin interaction.

## Application surfaces

- Web: v3 vector symbol, Inter, direct token-based styles and signature Financial Compass / Next Step treatments.
- Mobile: shared Material theme, Inter, 48-pixel target baseline and branded Next Step treatment.
- Store: v3 copy, app-icon direction and feature-graphic source; the Apricot accent sits below “clearer.” rather than crossing it.
- Documents, presentations, social and partner co-branding: controlled starter artwork under `brand/v3/templates/`.

The brand system does not activate financial products, providers, permissions, licences or store publication.

## Updating safely

1. Follow `brand/v3/BRAND_CHANGE_POLICY.md`.
2. Change approved values in `brand/opfin.tokens.json`.
3. Update the vector master only through a reviewed brand-system change.
4. Run `python3 scripts/sync-brand.py` when tokens change.
5. Run `node scripts/prepare-brand-assets.mjs` when vector/font-derived platform assets need regeneration.
6. Run `sh scripts/build-play-store-assets.sh` when store artwork changes.
7. Commit sources, generated assets, manifest and relevant documentation together.
8. Run the required Web/mobile/security jobs and complete visual review.

`python3 scripts/sync-brand.py --check` rejects token drift and verifies required contrast pairs. `python3 scripts/verify-security-controls.py` validates v3 provenance, rejects retired Web palette/type values in active style sheets, verifies platform theme wiring and validates asset hashes.

## External release gates

Do not call v3 frozen merely because source and templates exist.

Before changing the status to Brand System 3.0.0 Frozen, complete:
- `brand/v3/SCREENSHOT_CAPTURE_STANDARD.md` against the exact signed release candidate;
- `brand/v3/TRADEMARK_CLEARANCE.md` through official registers/legal review;
- `brand/v3/VISUAL_QA_CHECKLIST.md` on representative real/rendered outputs;
- public Play Console replacement and verification.

Source changes, CI renders and illustration boards are not evidence that a production Android build was captured, tested or published.
