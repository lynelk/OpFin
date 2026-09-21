#!/usr/bin/env python3
"""Fail CI when current product/API documentation obviously drifts from changed code."""

from __future__ import annotations

import argparse
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

REQUIRED_CURRENT_DOCS = [
    "README.md",
    "AGENTS.md",
    "SECURITY.md",
    "docs/README.md",
    "docs/DEVELOPER_START_HERE.md",
    "docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md",
    "docs/product/OPFIN_PRODUCT_BLUEPRINT.md",
    "docs/product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md",
    "docs/architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md",
    "docs/manuals/OPFIN_USER_MANUAL.md",
    "docs/manuals/OPFIN_TRAINING_MANUAL.md",
    "docs/manuals/OPFIN_OPERATIONAL_MANUAL.md",
    "docs/manuals/OPFIN_UAT_MANUAL.md",
    "docs/LAUNCH_CUSTOMER_JOURNEY.md",
    "docs/UMRA_DIGITAL_LENDING_CONTROLS.md",
    "apps/api/README.md",
    "apps/api/docs/README.md",
    "apps/api/docs/api/API_QUICK_REFERENCE.md",
    "apps/api/docs/api/current-endpoints.md",
    "apps/api/docs/api/frontend-backend-contract.md",
    "apps/client/README.md",
    "apps/web/README.md",
]

FORBIDDEN_CURRENT_PHRASES = {
    "apps/client/README.md": ["A new Flutter project."],
    "apps/web/README.md": ["Home | Borrow | Save | Grow | More", "lynelk/OpFin-BE"],
    "apps/web/docs/api/frontend-backend-contract.md": ['"password": "password"', "investor-demo screens"],
    "apps/web/docs/frontend/screen-map.md": ["phone/password login", "backend offer module is missing"],
    "packages/contracts/README.md": ["initial migration deliberately does not invent"],
}

def git_changes(base: str | None) -> set[str]:
    if not base:
        return set()
    cmd = ["git", "diff", "--name-only", base, "HEAD"]
    completed = subprocess.run(cmd, cwd=ROOT, check=True, capture_output=True, text=True)
    return {line.strip() for line in completed.stdout.splitlines() if line.strip()}

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base", help="Git base ref for drift checks.")
    args = parser.parse_args()

    errors: list[str] = []
    for relative in REQUIRED_CURRENT_DOCS:
        path = ROOT / relative
        if not path.exists() or path.stat().st_size < 80:
            errors.append(f"Missing or empty current documentation: {relative}")

    for relative, phrases in FORBIDDEN_CURRENT_PHRASES.items():
        path = ROOT / relative
        if not path.exists():
            continue
        content = path.read_text(encoding="utf-8", errors="replace").casefold()
        for phrase in phrases:
            if phrase.casefold() in content:
                errors.append(f"Stale phrase in current documentation {relative}: {phrase}")

    changes = git_changes(args.base)
    if changes:
        route_changed = any(path.startswith("apps/api/routes/") for path in changes)
        api_contract_changed = any(
            path.startswith(("apps/api/app/Http/Controllers/Api/", "apps/api/app/Services/", "apps/api/database/migrations/"))
            for path in changes
        )
        client_changed = any(path.startswith("apps/client/lib/") for path in changes)
        web_changed = any(path.startswith("apps/web/src/") for path in changes)

        if route_changed and "apps/api/docs/api/current-endpoints.md" not in changes:
            errors.append("API routes changed without updating apps/api/docs/api/current-endpoints.md")

        if api_contract_changed and not any(
            path.startswith("apps/api/docs/") or path.startswith("docs/")
            for path in changes
        ):
            errors.append("Backend/API contract changed without a current documentation update")

        if client_changed and not (
            "apps/client/README.md" in changes
            or "docs/LAUNCH_CUSTOMER_JOURNEY.md" in changes
            or "docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md" in changes
        ):
            errors.append("Flutter customer journey changed without client/journey documentation")

        if web_changed and not (
            "apps/web/README.md" in changes
            or any(path.startswith("apps/web/docs/") for path in changes)
            or any(path.startswith("docs/") for path in changes)
        ):
            errors.append("Web/admin workflow changed without web/current documentation")

    if errors:
        print("Documentation drift check failed:")
        for error in errors:
            print(f" - {error}")
        return 1

    print("Documentation drift check passed.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
