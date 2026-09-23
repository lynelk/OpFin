#!/usr/bin/env python3
"""Check publication-facing OpFin documentation for obvious editorial release blockers."""

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

PUBLIC_FILES = [
    "README.md",
    "docs/CURRENT_STATE.md",
    "docs/README.md",
    "docs/BRAND_IMPLEMENTATION.md",
    "docs/GOOGLE_PLAY_ACCOUNT_DELETION.md",
    "docs/LAUNCH_CUSTOMER_JOURNEY.md",
    "docs/manuals/OPFIN_USER_MANUAL.md",
]

CONTROLLED_EXTERNAL_FILES = [
    "docs/product/OPFIN_PRODUCT_BLUEPRINT.md",
    "docs/product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md",
    "docs/product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md",
    "docs/UMRA_DIGITAL_LENDING_CONTROLS.md",
    "docs/COMMUNITY_FINANCE_AND_SACCO_CORE.md",
    "docs/architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md",
    "apps/api/docs/README.md",
    "apps/api/docs/api/API_QUICK_REFERENCE.md",
    "apps/api/docs/api/current-endpoints.md",
    "apps/api/docs/api/frontend-backend-contract.md",
]

BLOCKERS = [
    re.compile(r"\bTODO\b", re.I),
    re.compile(r"\bTBD\b", re.I),
    re.compile(r"\bFIXME\b", re.I),
    re.compile(r"\[CONFIRM\b", re.I),
    re.compile(r"\[DEDICATED REVIEW ACCOUNT\]", re.I),
    re.compile(r"\[VERIFY [^\]]+\]", re.I),
    re.compile(r"example\.com", re.I),
]

def main() -> int:
    failures = []
    checked = PUBLIC_FILES + CONTROLLED_EXTERNAL_FILES

    for rel in checked:
        path = ROOT / rel
        if not path.exists():
            failures.append(f"{rel}: missing")
            continue

        text = path.read_text(encoding="utf-8")
        if not text.lstrip().startswith("# "):
            failures.append(f"{rel}: missing level-one title")

        for pattern in BLOCKERS:
            if pattern.search(text):
                failures.append(f"{rel}: publication blocker matches {pattern.pattern}")

    if failures:
        print("Publication readiness check failed:")
        for item in failures:
            print(f" - {item}")
        return 1

    print(f"Publication readiness check passed for {len(checked)} current public/controlled-external documents.")
    print("Controlled release worksheets and historical evidence are classified separately in docs/PUBLICATION_REGISTER.md.")
    return 0

if __name__ == "__main__":
    sys.exit(main())
