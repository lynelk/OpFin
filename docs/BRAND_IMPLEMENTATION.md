# OpFin brand implementation

Status: Public brand implementation reference  
Updated: 23 September 2026  
Language: English (United Kingdom)

## Direction and provenance

This implementation follows the 15 September 2026 decision to keep and refine the existing OpFin monogram with the Indigo, Apricot and Warm Ivory direction. It is not the rejected Open Path symbol or the retired Pipiya identity. The earlier September guideline remains useful for typography, truthful communications and layout principles; its earlier blue/sky-blue palette is not the colour direction implemented here.

`brand/opfin.tokens.json` is the machine-readable implementation source, not a claim that a new externally approved brand-guideline edition has been issued. Current values are Indigo `#353B78`, Apricot `#F2B38D`, Warm Ivory `#FAF8F2`, Periwinkle `#E8EAF6` and Ink `#202436`, with separate supporting and semantic status colours.

The existing raster master is `apps/client/assets/logo.png`; its recorded Git blob is checked before deriving assets. The upper monogram is isolated from the supplied stacked lock-up at its genuine blank separation band, then resized/recoloured without replacing its geometry. Compact app icons contain the symbol, not a miniature unreadable wordmark. The original source is retained unchanged. Text displaying the application name beside the symbol is interface text and is not represented as an exact reconstruction of the supplied vector wordmark.

Inter is self-hosted and bundled, using the pinned official `rsms/inter` revision and licence recorded in the token file. The asset generator checks the exact official file digests; applications do not need a runtime request to an external font CDN. Licence notices are retained in the repository.

## Application surfaces

- Web marketing header/footer and portal sidebar: original OpFin symbol instead of O/OF placeholder tiles.
- Web application and website: shared colour tokens, Inter typography, indigo actions, light customer-facing surfaces and apricot accents. Existing semantic error/success/warning colours keep their meanings.
- Mobile: shared Material theme, brand-colour controls, Inter body text, enlarged-text support, 48-pixel minimum interactive controls in the theme and a branded splash screen.
- Legacy mobile presentation overrides: black-only text and primary buttons migrated to the shared tokens without changing lending, payment or API rules.
- Android launcher, iOS icon catalogue and web icon: assets derived from the same retained mark, with a SHA-256 provenance manifest.
- Marketing preview: explicitly labelled illustrative, not presented as a production Android screenshot. Product claims still require actual availability and regulatory/provider verification.

The customer navigation remains Home | Borrow | Save | Grow | More. Branding is not permission to add unavailable products or bypass approval, account-deletion, identity or financial controls.

## Updating the identity safely

1. Update approved token values in `brand/opfin.tokens.json`.
2. Run `python3 scripts/sync-brand.py` to regenerate CSS and Dart constants.
3. When an approved master or derived asset changes, review its provenance, run `node scripts/prepare-brand-assets.mjs` with the locked web dependencies installed, and review the result visually. The source hash is intentionally a review boundary, not a value to replace blindly.
4. Commit assets, their manifest and relevant documentation together.
5. Run the required web/mobile/security jobs before release.

`python3 scripts/sync-brand.py --check` rejects token drift and checks the specified normal-text colour pairs at a minimum 4.5:1 contrast ratio. `python3 scripts/verify-security-controls.py` verifies that both apps load the theme, rejects legacy black presentation overrides and validates recorded asset hashes. These checks are not a full accessibility audit or a visual review of every device and screen.

## Release review

Review login, registration, password recovery, OTP, the home screen, products/terms, application status, repayment screens, profile, help and account deletion on real release builds. Check enlarged text, keyboard focus, screen-reader labels, financial-number wrapping, low-resolution devices and provider error states. Keep UGX amounts and terms legible. Do not crop or obscure disclosures to make a screenshot look cleaner.

The release notes and store listing must describe the release actually tested. Source changes, CI renders and illustration boards are not evidence that the production Android build has been captured or published.
