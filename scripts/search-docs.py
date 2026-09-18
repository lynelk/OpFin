#!/usr/bin/env python3
"""Simple repository documentation search.

Usage:
  python3 scripts/search-docs.py "credit reporting"
  python3 scripts/search-docs.py "receipt" --api
"""

from __future__ import annotations

import argparse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def markdown_files(api_only: bool) -> list[Path]:
    paths: list[Path] = []
    roots = [ROOT / "apps/api/docs"] if api_only else [ROOT]
    for base in roots:
        for path in base.rglob("*.md"):
            parts = set(path.parts)
            if "node_modules" in parts or "vendor" in parts:
                continue
            paths.append(path)
    return sorted(set(paths))

def main() -> int:
    parser = argparse.ArgumentParser(description="Search OpFin Markdown documentation.")
    parser.add_argument("query", help="Words to search for.")
    parser.add_argument("--api", action="store_true", help="Search API documentation only.")
    parser.add_argument("--limit", type=int, default=40, help="Maximum matching files.")
    args = parser.parse_args()

    tokens = [token.casefold() for token in args.query.split() if token.strip()]
    if not tokens:
        parser.error("query must contain at least one word")

    results = []
    for path in markdown_files(args.api):
        content = path.read_text(encoding="utf-8", errors="replace")
        folded = content.casefold()
        if not all(token in folded for token in tokens):
            continue

        lines = content.splitlines()
        title = next((line[2:].strip() for line in lines if line.startswith("# ")), path.name)
        excerpts = []
        for index, line in enumerate(lines, start=1):
            if any(token in line.casefold() for token in tokens):
                excerpts.append(f"L{index}: {line.strip()}")
            if len(excerpts) >= 3:
                break
        results.append((path.relative_to(ROOT), title, excerpts))

    if not results:
        print("No matching documentation found.")
        return 1

    for path, title, excerpts in results[: args.limit]:
        print(f"\n{path} — {title}")
        for excerpt in excerpts:
            print(f"  {excerpt}")

    if len(results) > args.limit:
        print(f"\n{len(results) - args.limit} additional matching files omitted.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
