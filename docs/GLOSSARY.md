# OpFin glossary

Review date: 18 September 2026. Audience: customers, support, trainers and developers.

| Term | Plain-language meaning in OpFin |
| --- | --- |
| API | The controlled interface through which the app or another authorised system asks OpFin to read information or perform a task. |
| Endpoint | One API address and method for a particular operation. `GET /api/profile` reads the authenticated customer's profile. |
| Authentication | Establishing who is making a request. |
| Authorisation | Checking whether that person may perform this particular action on this particular record. Signing in does not grant access to every record. |
| OTP | One-time code used for a verification step. Customers must not share it with staff or helpers. |
| PIN | The customer's secret six-digit sign-in credential for the current mobile journey. |
| KYC | Identity verification, including the required identity evidence and provider-backed checks. Capturing a photograph alone does not mean verification succeeded. |
| NIN | National Identification Number, handled as sensitive identity information. |
| Credit profile | The server-maintained picture of identity, consent, scoring information and credit state. |
| Composite score | OpFin's combined score, retaining separately attributable source components. Missing source data must remain identified as unavailable. |
| Available loan limit | The currently available amount under the profile. It is not a guarantee that an application will be approved. |
| Affordability | Assessment of whether the customer can meet the proposed repayment obligations. |
| Offer | A specific set of loan terms and disclosures for the customer to review before acceptance. |
| Verified wallet | A wallet whose ownership and permitted use have been established through the applicable verification flow. |
| Pending | A request has not yet reached the confirmed final state. Do not describe pending money as received or repaid. |
| Finality | The verified outcome used by OpFin to recognise the payment or disbursement, rather than a provider's initial acknowledgement. |
| Idempotency key | A unique reference for one logical request, used to prevent duplicate processing when the same request is retried. |
| Reconciliation | Comparing financial records and provider evidence to identify and resolve differences. |
| Ledger | The controlled accounting record of financial postings. |
| Capability gate | A control that keeps a feature unavailable until the required permissions, readiness and approvals are satisfied. |
| CRB | Credit reference bureau, a source or recipient of governed credit information under the applicable integration and consent rules. |
| UAT | User acceptance testing: checking that real workflows behave as expected in the stated build and environment. |
| CI | Automated checks run on proposed and committed changes. Passing CI is not the same as completing production acceptance. |
| `main` | The canonical repository branch. The deployed release may differ until the release process completes. |

For workflow details, use [the training foundation](TRAINING_AND_USER_GUIDE_FOUNDATION.md) and [the integrator guide](../apps/api/docs/api/INTEGRATOR_GUIDE.md). Definitions explain the source terminology; they do not replace contractual disclosures or compliance advice.
