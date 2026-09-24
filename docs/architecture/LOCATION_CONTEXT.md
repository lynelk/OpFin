# OpFin Location Context

Status: Canonical architecture contract  
Updated: 24 September 2026  
Language: English (United Kingdom)

## Purpose

Location is a supporting financial context, not a standalone OpFin product and not a continuous tracking system.

OpFin may use location only when it helps a person or authorised organisation complete a defined financial task, such as:

- finding participating service points;
- recording a Saving Group, Investment Club or SACCO operating area;
- recording a group meeting place;
- attaching a location to a physical investment or asset;
- recording an insured-risk location;
- recording an incident location when relevant to an insurance claim; or
- describing an approved partner service network.

Location is not a credit-score input by default.

## Product principles

1. **Foreground or manual only.** OpFin does not request background location permission or continuously track a customer.
2. **Approximate by default.** Personal service discovery uses approximate coordinates even when the device can provide more precision.
3. **Precision follows purpose.** Exact coordinates are reserved for tasks such as a physical asset, insured risk or claim incident where the task genuinely needs them.
4. **No location requirement for baseline OpFin.** A customer can manage money, save, borrow, join a group and use other baseline capabilities without sharing location unless a selected product/task specifically requires it.
5. **Purpose-bound consent.** The stored consent purpose must match the task that uses the location.
6. **Server-authoritative privacy.** Customers cannot self-mark a location as regulator/partner verified.
7. **No credit eligibility.** Every Location Context is marked credit_decision_eligible=false. Any future geographic underwriting signal requires a separately governed, consented, validated alternative-data policy.
8. **Group context is not member surveillance.** Group operating/meeting locations do not expose member home locations.
9. **Partner analytics are aggregated.** Operations insights use Financial Space and partner-service-point context with a minimum cohort of five; individual customer pins are excluded.
10. **Provider independence.** Google Maps is an adapter behind the OpFin API, not a domain dependency.

## Domain model

location_contexts stores a normalised, purpose-bound record:

- stable public ID;
- subject type and subject ID;
- optional Financial Space and user ownership references;
- purpose;
- source;
- precision level;
- place name and formatted address;
- Google Place ID where available;
- Plus Code where available;
- country, region, district and locality;
- latitude/longitude where the purpose needs coordinates;
- accuracy in metres where supplied by the device;
- consent purpose;
- verification status;
- captured/verified timestamps;
- metadata;
- credit_decision_eligible=false.

Supported subjects:

- user
- financial_space
- financial_asset
- protection_policy
- protection_claim
- partner_service_point

Supported purposes:

- personal_service_discovery
- group_operating_area
- group_meeting_place
- insured_risk_location
- investment_asset_location
- claim_incident_location
- partner_service_point
- partner_aggregate_insights

The API validates that purpose and subject type are compatible.

## Precision

| Precision | Storage behaviour | Typical use |
| --- | --- | --- |
| country | no coordinate storage | national context |
| region | rounded to one decimal | broad regional context |
| district | rounded to two decimals | district/municipal context |
| locality / approximate | rounded to three decimals | service discovery, group operating context |
| precise | up to seven decimals | physical asset, insured risk or claim incident |

personal_service_discovery is forced to approximate even if a client asks for precise storage.

## Google Maps adapter

Google Maps Platform credentials remain server-side.

Supported adapter functions:

- Places Autocomplete (New) for user-selected place search;
- Place Details for canonical name/address/coordinates as part of a purpose-bound save workflow;
- reverse geocoding for an explicitly requested foreground device location as part of that same save workflow;
- Static Maps for lightweight embedded preview;
- Routes for explicit distance/direction tasks where enabled;
- standard Maps URLs for opening directions/search in the user's map application.

The Flutter application does not embed a full Google Maps SDK. It uses the phone's native foreground location service and the OpFin API.

Environment gates:

GOOGLE_MAPS_PLATFORM_ENABLED=false  
GOOGLE_MAPS_SERVER_API_KEY=  
GOOGLE_MAPS_STATIC_MAPS_ENABLED=false  
GOOGLE_MAPS_ROUTES_ENABLED=false

Missing credentials fail closed for Google-dependent functions. Manual location remains available.

## Mobile channels

### App

The App can:

- request approximate foreground location for nearby-service discovery;
- request precise foreground location only for an asset/risk/claim task;
- search/select a Google place when the adapter is configured;
- enter an area/address manually;
- display a small authenticated Static Map preview;
- open the location in the installed map application.

Android requests coarse location by default. Fine location is requested only for a task explicitly marked precise.

iOS requests **When In Use** location only. Background location permission is not configured.

### USSD

USSD does not collect device GPS. A user may continue the task in the App or through an authorised assisted workflow when physical location is genuinely required.

### WhatsApp/SMS

WhatsApp/SMS do not silently infer or store device location. A future location-message workflow must still bind the location to an explicit OpFin task and consent purpose before storage.

## Financial Spaces and groups

Saving Groups, Investment Clubs and SACCOs can record:

- an operating area, normally locality/district precision; and
- a meeting place, where members need a recognisable place/directions.

Ordinary members may read authorised group location context. Only approved group administrative roles may change it.

## Assets and investments

A recorded financial asset can have an investment_asset_location.

This is intended for physical property, farms, project sites, warehouses and other location-dependent investments. Non-physical assets do not require a location.

## Insurance and protection

A protection policy may have an insured_risk_location when the insured risk is tied to a physical site.

A claim may have a claim_incident_location when relevant to insurer/underwriter assessment.

Location does not change claim decision authority. The disclosed insurer/underwriter remains responsible for policy/claim decisions.

## Partner service networks

Operations can attach verified/partner-confirmed service points to an approved partner.

Customers can run a one-shot nearby-service search using approximate current location. That query location is not stored by the nearby-service endpoint.

Programme-partner users can view service points belonging to their own institution's partner record. They do not receive customer/member pins.

## Aggregate insights

Operations can view aggregate location coverage grouped by:

- country;
- region;
- district/area;
- subject type; and
- purpose.

Rows below five records are suppressed.

Individual user Location Contexts are not included in the aggregate operations endpoint.

## Security and deletion

Location records use normal OpFin authentication and subject ownership/membership controls.

Soft-deleted Financial Space memberships do not grant access.

Personal Location Contexts are optional customer context and are purged when account deletion completes, except where a separate legally retained regulated record explicitly requires its own location evidence.

Google server API keys are never returned in API responses and are not embedded in Flutter or Web builds.

## Activation

Code deployment and Google activation are separate states.

The release may deploy safely with Google Maps disabled. Google-dependent functions become live only when:

1. an approved Google Cloud/Maps Platform project exists;
2. the required APIs are enabled;
3. a server-restricted API key is configured;
4. billing/quotas and usage alerts are configured;
5. production privacy/legal review is complete; and
6. static maps/routes are enabled only where required.

Manual location and server-side privacy controls remain available without activating Google Maps.
