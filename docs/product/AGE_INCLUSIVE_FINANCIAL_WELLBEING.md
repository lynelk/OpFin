# OpFin age-inclusive financial wellbeing and coaching policy (proposed)

Status: **Product/design proposal only**. No implementation or legal approval implied.
Created: 17 October 2026.
Scope: OpFin web, Flutter, assisted/low-data channels, API eligibility, consent, Financial Spaces and external-service activation.

## Foundational principle

**Financial inclusion is a relationship entitlement, not a promise of access to every product.**

Every person should find a useful and respectful route to appropriate financial knowledge and tools in OpFin. If a regulated service is unavailable due to age, capacity, consent, provider activation, product permissions or suitability, the experience must provide an age-appropriate, useful alternative. This principle does **not** overrule law, safeguarding, eligibility, child protection, financial disclosures, anti-fraud rules, or individual safety interventions.

**One OpFin; adaptable capabilities and protection.** Do not stereotype by gender, geography, balance, device, age or wealth. Use actual eligibility and user-selected intentions; learning, planning, and legitimate account capabilities remain distinct.

## Principles

1. **Never gate all financial education behind credit eligibility, NIN completion or a savings-provider account.** Publish privacy-preserving, low-bandwidth guest lessons, accessible without a permanent profile where feasible.
2. **Child account creation is separate from public guest learning.** Where personal data of a child is processed in Uganda, require the appropriate legal basis and prior verified parent/guardian consent or a separately applicable statutory basis, under the Data Protection and Privacy Act, section 8. Do not assume age alone supplies authority. Assess every market separately.
3. **Age-restricted services are server-enforced.** Rules include jurisdiction, verified age/capacity, exact provider/product, lawful guardian authority, required consent, KYC, account permissions, suitability and activation. UI hiding is not a control.
4. **Savings goals are not savings deposits.** Goal trackers, educational simulators and permitted personal records must not falsely represent regulated accounts, interest earned or protected balances. Real savings products require authorised partners, verified rules, guardian involvement where required, disclosures and confirmed transaction evidence.
5. **No child-directed credit marketing, behavioural underwriting, speculative investment nudges or engagement dark patterns.** A child may learn about credit without being solicited or prequalified for it.
6. **The coach always offers a *safe* next step.** It must not promise financial outcomes, bypass guardian duties, or advise a minor to work around restrictions. If immediate personal support is necessary, guide to a trusted person or authorised support channel without requesting sensitive secrets.
7. **Data minimisation and dignity.** Collect the minimum age or birth evidence necessary, limit the exposure and retention of children's personal/financial data, avoid default public profiles and leaderboards; obtain explicit lawful consent for any expanded processing. Guardians receive only the visibility legitimately authorised for that particular activity.
8. **Age transition must be governed.** Adult status should trigger a reviewed capability/consent/permission refresh, not automatically move, disclose or entitle customer money. Reconsider guardian access under applicable law.
9. **Accessibility across life stages.** Include age-appropriate reading levels, voice/audio where useful, local languages after review, screen readers, assisted capture and offline-aware educational assets; never lower security to enable assisted support.
10. **Measure outcomes, not compulsive use.** Learning comprehension, goal follow-through, satisfaction, privacy incidents and task completion matter more than minutes spent in-app.

## Example capability model (illustrative; not a legal eligibility matrix)

| Capability | Child / young person | Adult | Enforcement |
| --- | --- | --- | --- |
| Public financial education | Accessible without identifying or tracking the person, subject to safe content rules | Accessible | Content privacy, age-appropriate delivery |
| Private spending notes / financial goals | Only through a lawfully activated, protected account/guardian relationship where required | Available subject to account setup | Auth, child data consent, data minimisation |
| Planning simulations | Clear non-transactional learning, without real-money execution | Available | Educational disclaimer, no product execution |
| Regulated savings / deposits | Only where country/provider allows, and with verified parent/guardian authority where required | Subject to ordinary product eligibility | Server service eligibility, provider acceptance |
| Credit application / borrowing | Unavailable where child cannot lawfully contract or product forbids it; teach how borrowing works instead | Subject to credit, affordability and KYC controls | API reject + non-punitive UI and education |
| Investment products | No speculative execution or recommendation to minors; lawful guardian-managed products only when permitted | Suitability, product and jurisdiction gates | API, provider and risk/disclosure controls |
| Financial Space participation | Privacy-aware, verified guardian and role permissions as required | Role based | Exact Space authority, no personal-data leakage |

## Coach response contract

When a person requests unavailable functionality:
1. State what is not available **without blame**.
2. Give one factual, plain-language reason; avoid inferring more about the person.
3. Preserve the person's goal and offer a specific, eligible next step.
4. If a guardian or institution is required, describe the lawful process clearly and provide help.
5. Provide a clear way back to general guidance and support.

Example for a 15-year-old asking for a loan:

> **Borrowing isn't available for this account right now.** You can still make a plan for what you want to achieve, learn how credit works and build saving habits. **Create a goal** or **Explore money lessons**.

Example for regulated savings with guardian participation:

> **You can start with a savings goal today.** If you'd like a regulated savings account, we'll show eligible options and any parent or guardian steps required. Availability depends on your country and provider.

Example for someone in financial distress:

> **You still have options.** Let's review essential expenses, payments and available support one step at a time. Some financial products may not be available; practical guidance remains available.

## Required product changes and acceptance gates

- **Discovery P0:** map all existing web, Flutter, API, assisted/USSD and partner flows that collect age/date of birth or gate access; evaluate whether registration currently assumes all customers can consent or contract as adults.
- **Policy P0:** legal/privacy/safeguarding review by jurisdiction and service; data-processing inventory for children; verified parental authority flows and revocation; provider-specific savings and other product requirements.
- **Experience P0:** guest learning path; age-aware welcome; user-selected learning goals; no blank/locked Home; meaningful alternative when service restricted.
- **API P0:** versioned service eligibility decisions and machine-readable safe alternatives; fail-closed execution with friendly user messages. Every native endpoint must enforce, not just the Web.
- **Controls P0:** guardian verification and authority scoping; least-privilege Financial Space membership; safeguarding events; no marketing to children for age-restricted products.
- **Testing P0:** underage claims falsified, missing/unknown age, guardian pending/revoked, adult transition, cross-country users, age mismatch, shared devices, offline caches, unauthorised attempts, and no disclosure of another person's financial history.
- **Acceptance P1:** research with teens, guardians, educators, rural customers and accessibility specialists, with appropriate consent and safeguarding. Check comprehension, independent goal setting and perceived dignity.
- **Deployment:** do not activate regulated child products without signed provider and country permissions, internal financial-control testing and acceptance evidence.

## Uganda legal signposts requiring local counsel review

- Data Protection and Privacy Act, section 8: collection/processing of a child's personal data requires prior parent/guardian or other legally authorised consent unless a stated exception applies; section 9 addresses processing of financial personal data. Source: https://ulii.org/en/akn/ug/act/2019/9/eng@2023-12-31
- Contracts Act, section 10: general contractual capacity includes being 18 or older, alongside other conditions. Source: https://ulii.org/en/akn/ug/act/2010/7/eng@2023-12-31
- Examples that guardian-managed child savings products exist in Uganda, not blanket approval for OpFin or any user: https://www.housingfinance.co.ug/retail-banking/savings-account/totos-treasure-account/ and https://abccapitalbank.co.ug/personal/children-savings

This is a proposed cross-product standard. It does not assert that the existing OpFin implementation currently meets these controls.
