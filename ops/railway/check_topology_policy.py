#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
p = json.loads((ROOT / "ops/railway/topology-policy.json").read_text(encoding="utf-8"))
if not p.get("requiresExplicitOwnerConsentForNewRailwayResource"):
    raise SystemExit("Explicit owner consent for new Railway resources must remain mandatory.")
if p.get("permanentValidationServicesAllowed") is not False:
    raise SystemExit("Persistent validation services are forbidden.")
if p["workspaceCostPolicy"].get("railwayAgentHardLimitUsd") != 0:
    raise SystemExit("Railway Agent must remain disabled.")
if p["workspaceCostPolicy"].get("computeHardLimitUsd") != 10:
    raise SystemExit("Workspace compute hard-limit policy must remain USD 10.")
allowed=p["environments"]["production"]["allowedServices"]
if len(allowed) != len(set(allowed)):
    raise SystemExit("Duplicate approved service names are not allowed.")
print("OpFin Railway topology policy: PASS")
