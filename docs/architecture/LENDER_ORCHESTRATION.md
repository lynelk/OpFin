# Lender orchestration and affiliated credit

Status: Implementation contract; production activation remains separate
Updated: 25 September 2026

## Operating model

OpFin is Core Synergies' orchestration and infrastructure product. The selected institution is the lender of record. Core Synergies can participate as an affiliated lender through the same institution, product, decision, capital mandate, reservation and immutable offer lifecycle as independent lenders. Platform staff use their platform identities. Creating a lender profile creates an internal `partners` record (`LENDER-{institution_id}`) for funding/provider accounting, not a partner login or user.

The owner reports Core Synergies' UMRA licence. This change does not invent its exact registered entity details, licence number, scope, validity, store approval or funded balance. Enter those from the actual evidence. An inactive profile can be prepared while evidence is pending.

`institutions` records relationship, jurisdiction, regulator, licence class, authority basis/reference/expiry and whether rate changes need regulatory approval evidence. Accepted offers snapshot the actual institution as `lender_of_record`; legacy `regulated_provider` disclosure compatibility is retained. Regulatory pricing resolves country/product/licence class. A lender outside UMRA's scope can record another regulator, other authority or documented exemption. OpFin provides configuration, evidence and operational guidance; an admin record is not a regulator's finding or a store's approval.

## Platform administration

Open **Admin → Lenders & credit deployment** (`/admin/lending-platform`).

1. Create the lender with its actual legal identity and evidence. Choose **Affiliated** for Core Synergies; leave it inactive until ready.
2. Use its generated partner ID in **Essentials / capital mandates** to establish the funding pool. Existing maker-checker approval applies; a mandate owner cannot approve their own mandate.
3. Add products, country/currency, borrower-purpose and amount ranges, funding pool and repayment terms. Existing products cannot be reassigned to another lender. Daily/weekly/monthly/annual interest cycles and daily/weekly/fortnightly/monthly schedules supported by the pricing engine can be stored without old database enum restrictions. These are engine capabilities, not a claim that every product is authorised.
4. Record channel policy and supporting evidence. Keep unpublished products configured.
5. Select a deployment strategy, reason, optional per-loan cap and expiry. Delegate operations access when needed.

| Strategy | New origination behaviour |
| --- | --- |
| `withhold` (initial default and expired-strategy fallback) | Independent routes only |
| `external_first` | Independent eligible routes; affiliated fallback when none can serve the requested amount/purpose/channel |
| `affiliated_first` | Affiliated candidates appear first, then independent candidates |

Candidate eligibility here means active institution/product/term, complete non-pending lender authority, market/channel, amount/purpose and configured funding availability; Essentials also uses lender credit lines and service categories. It does not manufacture a lender's underwriting approval. Credit profile, KYC, consent, affordability and provider finality remain separate controls. The cap is **per affiliated loan**, not a total deployment budget; total capacity remains the approved funding pool's locked committed/reserved/deployed balance.

Only `platform_admin` can set strategy, relationship ownership, distribution policy or delegate access. Operations require `can_manage_platform_credit` for this configuration and affiliated decision/offer/term/capital controls. The flag is not mass assignable. Legacy institution/product forms are restricted to platform/operations roles and cannot assign affiliation or authority through mass assignment. General operations portfolio/support visibility remains governed by existing roles; this is management-access segregation, not a new confidential portfolio tenancy model.

Affiliated pools must link through their partner record to the same institution. Any known cross-institution pool assignment is rejected. Historical independent pools without an institution link retain compatibility; link those records during onboarding review. No default funded capital is created.

## Distribution configuration

`config/credit_distribution.php` holds reviewable defaults; `credit_distribution_rules` stores append-only admin revisions. A rule scopes channel, country, product classification, optional institution and either a loan product or an Essentials partner product. Product-specific rules outrank lender, country and classification scopes. Each scope's newest effective version supersedes earlier versions; expiry falls back to broader/default policy rather than reviving an old exception. Times are normalised to database timestamps.

Channels: `web`, `play_store`, `app_store`, `huawei_appgallery`, `whatsapp`, `ussd`; legacy `android` aliases Google Play. Each revision records available/unavailable/review, optional minimum duration and APR cap, source reference/URL, reason, actor and effective interval. Rules replace the complete selected policy, so record all applicable constraints when making an exception. Do not treat a lender's authority as evidence that a store permits a product.

Defaults reviewed 25 September 2026:

- Google Play personal loans: more than 60 days to full repayment. The default is for the current Uganda launch; an additional country pack must capture territory-specific rules (including US APR restrictions) before activation.
- Apple personal loans: more than 60 days and APR at most 36%, including fees. Exact cash-flow APR is evaluated at offer/quote time, not inferred from a monthly rate.
- Unknown store classifications and Huawei: explicit review required until applicable evidence is recorded. No Huawei financial-product approval is assumed.
- Non-store channels: no store tenure/APR defaults; lender and market financial rules still apply.

Availability is checked in discovery, application routing, offer/quote generation and first acceptance. Acceptance uses quoted economics; later product edits do not reprice an existing quote. A new restriction prevents a new commitment. Pending/disbursed replay and servicing remain on their existing lifecycle. API clients must send the actual build channel; the request field is not device attestation, and trusted distribution attestation remains an expansion task.

Google's policy applies to facilitators and lead generators as well as direct lenders: https://support.google.com/googleplay/android-developer/answer/9876821
Apple guideline 3.2.2(ix): https://developer.apple.com/app-store/review/guidelines/#business

## Markets and release boundary

`OPFIN_CREDIT_ENABLED_COUNTRIES=UG` activates only the initial credit market. Product country/currency can be prepared independently. The current money route remains bound to `services.mobile_money.currency`; metadata does not enable foreign KYC, tax, credit reporting, FX or settlement. Country packs need implementation and certification before their activation. See the [development roadmap](../development/2026-09-25-lending-platform-roadmap.md) and [expansion backlog](../development/CROSS_BORDER_ROADMAP.md).

Run the forward migration after backup and reviewed CI, before deploying the API and clients. Existing institutions remain independent by default; no record is silently marked Core Synergies. Complete legacy authority records and link every capital-mandate partner to its actual institution before any new lending. Missing ownership is not treated as a legacy exemption. Existing loan servicing preserves the legacy configured or snapshotted policy class when no new lender disclosure exists. Rollback removes new configuration tables/columns, so export policy/audit evidence first; widened term string columns intentionally remain widened to preserve already-created terms. Prefer a forward fix once products use the new configuration.

This branch changes cash-credit and Essentials lending. Participatory finance, asset finance and provider-specific products retain their existing activation controls. No product family's legal/store approval is inferred from another family's rule.


The mobile application refreshes routing with the entered amount/purpose and asks the borrower to review any changed lender/term. Essentials applies strategy and distribution rules before outbound eligibility requests, not merely when hiding the response. Existing lines and the displayed overall limit are channel-filtered; an actual quote may perform one fresh amount/category-specific eligibility check to discover a permitted fallback without creating hidden affiliate approvals while withholding credit.
