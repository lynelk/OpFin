#!/usr/bin/env python3
"""Validate the proposed institution capability register without granting authority.

The register catalogues product scope and source evidence. A passing validation
does not attest live providers, legal permissions, financial acceptance or pricing.
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REGISTER = ROOT / "docs/product/OPFIN_INSTITUTIONAL_CAPABILITY_REGISTER.json"
VALID_PRIORITIES = {"P0", "P1", "P2"}
VALID_STATUSES = {
    "source_foundation",
    "candidate_source",
    "acceptance_gap",
    "target_scope",
}


def check() -> list[str]:
    errors: list[str] = []
    try:
        data = json.loads(REGISTER.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        return [f"Cannot read institutional register: {exc}"]

    if data.get("schema_version") != 1:
        errors.append("schema_version must be 1")
    if data.get("status") != "proposed_b2b_register_not_a_release_attestation":
        errors.append("Register must remain explicitly non-attesting")
    if set(data.get("status_legend", {})) != VALID_STATUSES:
        errors.append("Status legend must define exactly the accepted source maturity states")
    if not data.get("reviewed_date"):
        errors.append("reviewed_date is required")

    institutions = data.get("institutions")
    if not isinstance(institutions, list) or not institutions:
        return errors + ["institutions must be a non-empty list"]

    seen_codes: set[str] = set()
    count = 0
    for index, row in enumerate(institutions):
        if not isinstance(row, dict):
            errors.append(f"institution #{index}: must be an object")
            continue
        label = str(row.get("label") or index)
        code = row.get("code")
        if not isinstance(code, str) or not code.strip():
            errors.append(f"{label}: missing stable code")
        elif code in seen_codes:
            errors.append(f"{label}: duplicate code {code}")
        else:
            seen_codes.add(code)

        if row.get("priority") not in VALID_PRIORITIES:
            errors.append(f"{label}: invalid priority")
        for key in ("model", "journey", "client", "financial_activation_gate",
                    "offline_scope", "pricing_hypothesis", "north_star_metric"):
            if not isinstance(row.get(key), str) or not row[key].strip():
                errors.append(f"{label}: missing {key}")
        if row.get("commercial_price_approved") is not None:
            errors.append(f"{label}: commercial price cannot be claimed without evidence")
        if row.get("financial_activation_status") != "not_attested_by_this_register":
            errors.append(f"{label}: do not claim activated or inactive finance status")
        if "financially_activated" in row:
            errors.append(f"{label}: ambiguous financial activation boolean is forbidden")

        roles = row.get("roles")
        if not isinstance(roles, list) or not roles:
            errors.append(f"{label}: roles required")
        paths = row.get("source_evidence_paths")
        if not isinstance(paths, list) or not paths:
            errors.append(f"{label}: at least one source reference required")
        else:
            for source in paths:
                if not isinstance(source, str) or not source.strip():
                    errors.append(f"{label}: invalid evidence path")
                elif not (ROOT / source).is_file():
                    errors.append(f"{label}: missing evidence source {source}")

        modules = row.get("modules")
        if not isinstance(modules, list) or not modules:
            errors.append(f"{label}: at least one module required")
            continue
        seen_modules: set[str] = set()
        for module in modules:
            count += 1
            if not isinstance(module, dict):
                errors.append(f"{label}: invalid module object")
                continue
            key = module.get("key")
            if not isinstance(key, str) or not key.strip():
                errors.append(f"{label}: module missing key")
            elif key in seen_modules:
                errors.append(f"{label}: duplicate module {key}")
            else:
                seen_modules.add(key)
            if module.get("status") not in VALID_STATUSES:
                errors.append(f"{label}/{key}: unknown status")
            if not module.get("notes"):
                errors.append(f"{label}/{key}: evidence/gap note is required")
            if module.get("acceptance") != "pending_independent_evidence":
                errors.append(f"{label}/{key}: accepted status requires external evidence, not register edits")

    if not errors:
        print(f"Institutional register structurally valid: {len(institutions)} institution types, {count} capability areas.")
        print("Validation is structural only; provider, regulated and financial acceptance remain unverified.")
    return errors


if __name__ == "__main__":
    problems = check()
    for message in problems:
        print(f"ERROR: {message}", file=sys.stderr)
    sys.exit(1 if problems else 0)
