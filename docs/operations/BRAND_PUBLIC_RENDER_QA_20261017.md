# OpFin Brand System v3: Public rendering evidence

**Date:** 17 October 2026. Tested against the public deployed Web application at `https://opfin-web-production.up.railway.app`. Automated browser: headless Chromium, reduced-motion preference, no authenticated customer data.

| Surface | Widths tested (px) | Result |
| --- | --- | --- |
| Home `/` | 320, 375, 768, 1440 | HTTP 200, one H1, no horizontal overflow, no broken images after lazy loading, no uncaught page exceptions |
| Learning `/learn` | 320, 375, 768, 1440 | HTTP 200, one H1, no horizontal overflow, no broken images after lazy loading, no uncaught page exceptions |

The footer symbol is lazy-loaded and did not load before scrolling in the first run. After explicit image visibility/decoding it returned correctly. Initial failure was a **test timing false positive**, not a shipping defect.

Existing vector checks passed for four core outline-only wordmark/symbol/lock-up masters. This automated public test is **not** a substitute for accessibility testing with assistive technologies, an authenticated small-screen audit, print proofs, signed iOS/Android artwork approval or legally reviewed trademark clearance.

**Brand System v3 remains release candidate. Trademark is NOT CLEARED.** Recorded for #103, not final frozen sign-off.
