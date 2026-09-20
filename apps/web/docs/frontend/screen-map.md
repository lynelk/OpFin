# OpFin web screen map

Updated: 18 September 2026

The exact Next.js router is authoritative. This file describes product areas, not every route filename.

## Customer

- login / account access;
- dashboard/Home borrower state;
- KYC and consent;
- loan application;
- decision/status;
- formal offer review/acceptance;
- active loan/account/schedule;
- support/complaints;
- profile/privacy/deletion;
- selected wellbeing/connected-finance surfaces where activated.

Formal offer review includes cost/default/complaint disclosures and separate credit-information reporting consent.

## Admin / operations

- operational dashboard;
- credit review;
- reconciliation;
- immutable ledger;
- support/complaints with regulatory due/SLA state;
- compliance centre;
- regulatory report/evidence-pack detail;
- governance/security/audit;
- product/workflow administration.

The Compliance Centre now covers UMRA credit-information exchange, books/records, NPL/default-interest controls, transaction receipts, complaints and term/guarantor controls.

## Product-gated areas

Savings, protection, investments, employer, community/SACCO, asset finance and participatory finance may have implemented surfaces but remain external-provider/regulatory gated unless activated.

They should not be promoted into primary borrower navigation merely because routes exist.

## Authentication

Current customer sign-in is phone + six-digit PIN. New registration verifies phone by OTP before names/PIN.

## Route/API discovery

```bash
cd apps/web
find src/app -name 'page.tsx' | sort

cd ../..
python3 scripts/search-api.py "credit"
```

## Documentation rule

When a workflow changes materially, update this map or the corresponding current product/API guide in the same PR.


## Financial Spaces and Workspaces — 20 September 2026

The current Web experience includes a Financial Spaces index and Space detail/workspace context. The product proposition is **understand, manage, plan and improve your money** across Personal, Household, Savings Group and authorised organisation contexts. Web is an enhancement for Individuals/Savings Groups and the deeper operating surface for Business/Employer, SACCO and regulated-partner administration.

Do not restore lending as the sole organising principle for Home/navigation. Borrowing remains one financial capability alongside everyday money, budgeting/goals, debt/receivables, assets/net position, saving, investment and protection.
