# OpFin Lightweight & Sponsored Data Standard

Status: implementation and release-control standard  
Established: 27 September 2026  
Requirement: OPF-FEAT-0021

## Purpose

OpFin is designed for customers who may use low-cost devices, unreliable connections and expensive mobile data. Lightweight operation is therefore a product requirement, not a late optimisation. Sponsorship/zero-rating is a separate carrier treatment: OpFin can make traffic eligible and measurable, but may describe it as sponsored only when the relevant operator has confirmed the exact production boundary.

## Architecture

Measurement is three-sided:

1. **Client ledger** records aggregate application-layer upload/download bytes, feature, operation, request count, failure count and sponsorship class. It never stores request/response payloads, NINs, PINs, OTPs, tokens or raw financial data.
2. **API/edge measurement** adds a correlation ID, records application-layer bytes in/out, route template, feature, operation, status, duration and configured sponsorship class. Query strings and payload bodies are excluded.
3. **Operator reconciliation** compares the approved whitelist and carrier usage statement against OpFin aggregates. Carrier billing records remain authoritative for whether data was actually charged/sponsored.

`distribution/sponsored-data/whitelist-manifest.json` is the controlled hostname boundary.

## Sponsorship states

- `sponsored`: only when the release/environment has operator-confirmed sponsorship configured for the exact client-facing origin (scheme, host and effective port).
- `non_sponsored`: traffic to an origin outside the approved OpFin client boundary.
- `unknown`: OpFin traffic whose carrier billing treatment is not confirmed.

The App must never convert `unknown` to `sponsored` merely because the request used an OpFin hostname.

## Initial data budgets

These are release thresholds to measure and refine with actual device/MNO evidence:

| Journey | Initial application-layer target |
| --- | ---: |
| Authenticated launch | <= 50 KB |
| OTP/login | <= 100 KB, excluding carrier SMS |
| Home refresh | <= 100 KB |
| Credit profile refresh | <= 75 KB |
| Basic finance application | <= 100 KB, excluding identity evidence |
| Repayment/transaction | <= 50 KB |
| 25-row activity page | <= 100 KB |
| One ordinary offline event | <= 64 KB |
| One offline sync batch | <= 256 KB / 50 events |
| Static map preview | <= 100 KB and on demand |
| Mobile KYC three-photo package | <= 1.5 MB |
| Routine active-user month | target <= 15 MB, excluding KYC and app-store updates |

A budget breach does not justify dropping financial evidence. It requires redesign, pagination/compression or a documented reviewed exception.

## Mobile implementation rules

- All ordinary OpFin HTTP traffic uses the metered client in `apps/client/lib/services/opfin_http.dart`.
- No feature may silently introduce a direct third-party hostname.
- Prefer one purpose-built aggregate response to several chatty screen-load calls where semantics remain clear.
- Use pagination, conditional/delta retrieval and explicit refresh rather than continuous polling.
- Offline queues are bounded; oversized actions require reconnection rather than uncontrolled local growth.
- Offline queues must not contain PINs, OTPs, access tokens, NINs, identity images, raw documents or provider payloads.
- Large uploads must be compressed/resized before sending and must show recoverable failure behaviour.
- Background/nonessential traffic must yield to customer actions.

## KYC

The mobile camera path resizes/compresses images before upload, caps each image, and caps the three-image mobile package at 1.5 MB. The API independently enforces the mobile package budget. Evidence quality remains mandatory; a low-data target is never permission to accept illegible identity evidence.

## Release gates

Package gates currently enforce these initial engineering ceilings:

- Android App Bundle distribution artefact: 60 MiB;
- universal Android APK: 60 MiB;
- each architecture-specific Android APK: 30 MiB;
- uncompressed iOS `Runner.app`: 120 MiB.

The AAB is an upload/distribution container and is not treated as the customer's installed download size. Architecture-specific APKs provide the stricter repository proxy for Android device payload size. Store-reported/device download measurements remain final acceptance evidence. The iOS figure is likewise an engineering artefact ceiling, not a claim about App Store cellular download size.

Each signed candidate records:

- AAB/APK/IPA relevant binary/download size;
- first-run network transfer;
- login and Home transfer;
- representative finance/repayment transfer;
- KYC package transfer;
- offline batch limits;
- new/changed client-facing hosts;
- variance from the previous accepted release.

The repository check rejects unapproved literal client hosts and direct static `package:http` calls that bypass the metered layer. Device/proxy/MNO measurements provide final acceptance evidence.

## External activation

MTN/Airtel or another operator must separately approve/configure zero-rating. App-store downloads, WhatsApp, external websites and mapping apps remain outside the OpFin sponsored boundary unless explicitly agreed by the relevant carrier. Cito may fulfil separately purchased data/airtime resources, but it does not manufacture MNO zero-rating.

## Governance

Every material PR assesses lightweight/sponsored-data impact under the Delivery & Feature Register. A new hostname, SDK, media source, upload flow, polling loop or background job requires an explicit data-impact review. Operator references and commercial terms belong in controlled configuration/evidence, not hard-coded customer logic.
