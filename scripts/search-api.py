#!/usr/bin/env python3
"""Search the registered Laravel API route table by keyword."""

from __future__ import annotations

import argparse
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
API = ROOT / "apps/api"

def main() -> int:
    parser = argparse.ArgumentParser(description="Search OpFin registered API routes.")
    parser.add_argument("query", help="Keyword such as credit, receipt, umra, kyc or wallet.")
    args = parser.parse_args()
    query = args.query.casefold().strip()

    try:
        completed = subprocess.run(
            ["php", "artisan", "route:list", "--json"],
            cwd=API,
            check=True,
            capture_output=True,
            text=True,
        )
    except FileNotFoundError:
        print("PHP is not installed. Use apps/api/docs/api/API_QUICK_REFERENCE.md instead.")
        return 2
    except subprocess.CalledProcessError as exc:
        print(exc.stderr or exc.stdout)
        print("Unable to load Laravel routes. Run composer install in apps/api first.")
        return 2

    routes = json.loads(completed.stdout)
    matches = []
    for route in routes:
        haystack = " ".join(str(route.get(key, "")) for key in ("method", "uri", "name", "action", "middleware")).casefold()
        if query in haystack:
            matches.append(route)

    if not matches:
        print("No matching registered API routes.")
        return 1

    print(f"{'METHOD':<14} {'URI':<58} ACTION")
    print("-" * 110)
    for route in matches:
        print(f"{str(route.get('method','')):<14} {str(route.get('uri','')):<58} {route.get('action','')}")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
