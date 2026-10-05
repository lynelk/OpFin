# Universal Asset Registry contract

Status: Current source contract (foundation slice; not production-activated)  
Reviewed: 4 October 2026  
Language: English (United Kingdom)  
Requirements: CAP-002, DEV-001 and AUTO-001 identity, AST-001 asset classes, DEV-002 duplicate-finance and stolen-asset controls. Register row OPF-FEAT-0010.

An Asset Passport records an asset's identity, verification, liens and theft reports. All asset classes share one lifecycle; the identity evidence required depends on the class. The registry never controls a device (remote device controls, DEV-005, stay disabled), never values or moves money, and never proves ownership.

The registry is distinct from the financial-life balance-sheet assets at `/api/financial-spaces/{space}/assets`, which record what a customer owns and its value.

## Activation

The registry returns HTTP 503 until `OPFIN_ASSET_IDENTIFIER_KEY` is set to a random secret of at least 32 characters. That key is used for the keyed HMAC of every identifier. It is deliberately separate from `APP_KEY`, because rotating it requires re-hashing every stored identifier.

## Asset classes and identity evidence

| Family | Classes | At least one of |
|---|---|---|
| Device | `phone` | `imei` |
| Device | `tablet` | `imei`, `serial` |
| Device | `laptop` | `serial` |
| Vehicle | `car`, `motorcycle`, `electric_vehicle`, `commercial_vehicle` | `vin`, `chassis_number` |
| Productive | `solar_system`, `machinery`, `agricultural_equipment` | `serial` |

Optional identifiers are `engine_number` and `registration_plate`. Identifiers are normalised by removing spaces, hyphens, dots and slashes, and converting to upper case. Each type is then validated:

- **IMEI:** 15 digits with a valid Luhn check digit.
- **VIN:** 17 characters, excluding I, O and Q.
- **Chassis and engine numbers:** 5–30 letters and digits.
- **Serial:** 4–40 letters and digits.
- **Plate:** 2–12 letters and digits.

Only a keyed HMAC and a masked form, such as `********7518`, are stored. Responses never contain the raw identifier.

## Lifecycle

`registered → verified → encumbered → verified` (after a lien is released) and `disposed`. Either `review_required` or `reported_stolen` can interrupt this path.

| Step | Who | Rules |
|---|---|---|
| Register | Space manager (owner, administrator, treasurer and similar) | `Idempotency-Key` required; the same key with a different asset returns 409. If an identifier is already active on another passport or belongs to a stolen asset, the new passport is `review_required` with inactive identifiers. The registrant is never told about the other Space. |
| Verify | Platform admin or operations | The verifier must be a different person from the registrant (maker-checker). Requires a method (`physical_inspection`, `dealer_invoice`, `oem_record`, `registry_extract`) and an evidence reference. Verification confirms identifiers, not ownership or value. |
| Clear or reject a review | Platform admin or operations | `activate` succeeds only when no identifier is still active on another passport. `reject` closes the passport as `disposed`. A reason is required. |
| Register a lien | Platform admin or operations | Asset must be `verified`. The financing arrangement must belong to the same Financial Space and still be open. At most one active lien per asset, enforced by the service and a partial unique index. |
| Release a lien | Platform admin or operations | After the arrangement is settled, either role may release. Before settlement, only a platform administrator may, with a reason. |
| Report stolen | Space manager or the personal owner | Blocks new liens. An existing lien stays in place. |
| Recover | Platform admin or operations | Returns the asset to `encumbered`, `review_required`, `verified` or `registered`, whichever applied before the theft report. |
| Dispose | Space manager | Requires no active lien. Deactivates the identifiers so a later buyer can register the asset afresh. |

Every step needs an `Idempotency-Key`. Replaying a key for the same action returns the current passport; reusing it for another action returns 409. Lifecycle events are append-only (database triggers on PostgreSQL and SQLite) and are audited as `asset.<event>`.

## Endpoints

Space-scoped (`auth:sanctum`):

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/financial-spaces/{space}/asset-passports` | List this Space's passports (members) |
| POST | `/api/financial-spaces/{space}/asset-passports` | Register an asset (managers) |
| GET | `/api/financial-spaces/{space}/asset-passports/{asset}` | Passport with history (members) |
| POST | `/api/financial-spaces/{space}/asset-passports/{asset}/report-stolen` | Record a theft report |
| POST | `/api/financial-spaces/{space}/asset-passports/{asset}/dispose` | Record sale, trade-in, scrapping, transfer or write-off |

Platform (`role:platform_admin,operations`):

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/admin/asset-passports/review-queue` | Passports needing review, with the internal reason |
| POST | `/api/admin/asset-passports/identifier-check` | Registry state for an identifier: registered, active passport, active lien, reported stolen. Never returns the owner or Space |
| POST | `/api/admin/asset-passports/{asset}/verify` | Verify identity |
| POST | `/api/admin/asset-passports/{asset}/review` | Clear or reject a review |
| POST | `/api/admin/asset-passports/{asset}/encumbrances` | Register a lien against a financing arrangement |
| POST | `/api/admin/asset-passports/{asset}/recover` | Record recovery of a stolen asset |
| POST | `/api/admin/asset-encumbrances/{encumbrance}/release` | Release a lien |

## Not yet in place

- Liens reference `financing_arrangements` only. Legacy loans need a compatibility adapter.
- Valuation, inspection, protection/warranty and recovery workflows are later AUTO-001 and DEV-004 slices.
- Supplier settlement and merchant-collusion analytics are later DEV-002 slices.
- The Product Factory is PF-001.
- There are no App or Web journeys yet.
