#!/usr/bin/env python3
"""Check local Markdown links in current OpFin documentation (no network calls).

Historical evidence is intentionally not reclassified or rewritten by this check.
Use --all to audit every tracked Markdown file outside the default release gate.
"""
from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[1]
CURRENT = [
    "README.md", "API.md", "AGENTS.md",
    "apps/api/README.md", "apps/web/README.md", "apps/client/README.md",
    "apps/api/docs/README.md",
    "docs/README.md", "docs/CURRENT_STATE.md", "docs/DEVELOPER_START_HERE.md",
    "docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md", "docs/PUBLICATION_STANDARD.md",
    "docs/PUBLICATION_REGISTER.md",
    "docs/operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md",
    "docs/developer/API_AND_AGENT_PLATFORM.md",
    "docs/product/OPFIN_PRODUCT_BLUEPRINT.md",
    "docs/product/OPFIN_ESSENTIALS.md",
    "docs/product/FINANCIAL_INTELLIGENCE.md",
    "docs/product/OPFIN_CANONICAL_PRODUCT_BREAKDOWN.md",
    "docs/product/OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md",
    "docs/development/FINANCIAL_SPACES_COMMERCIAL_OPERATIONS_2026-09-29.md",
    "docs/development/SACCO_AND_WHITE_LABEL_COMPLETION_2026-09-29.md",
]
INCLUDE_DIRS = ("docs/manuals", "apps/api/docs/api")
LINK = re.compile(r"(?<!!)\[[^\]\n]*\]\(\s*(<[^>]+>|[^\s)]+)(?:\s+['\"][^\n]*?['\"])?\s*\)")
SCHEMES = re.compile(r"^[a-z][a-z0-9+.-]*:", re.IGNORECASE)


def current_paths(root: Path) -> list[Path]:
    paths = [root / name for name in CURRENT]
    for directory in INCLUDE_DIRS:
        paths.extend((root / directory).glob("*.md"))
    return sorted(set(paths))


def all_paths(root: Path) -> list[Path]:
    proc = subprocess.run(
        ["git", "-C", str(root), "ls-files", "--", "*.md", "*.mdx"],
        check=True, capture_output=True, text=True,
    )
    return [root / name for name in proc.stdout.splitlines() if name]


def problems(root: Path, paths: list[Path]) -> tuple[list[str], int]:
    issues: list[str] = []
    checked = 0
    root = root.resolve()
    for path in paths:
        try:
            relative = path.relative_to(root)
        except ValueError:
            issues.append(f"{path}: input path lies outside repository root")
            continue
        if not path.is_file():
            issues.append(f"{relative}: referenced publication document is missing")
            continue
        try:
            content = path.read_text(encoding="utf-8")
        except UnicodeError:
            issues.append(f"{relative}: cannot decode Markdown as UTF-8")
            continue
        # Ignore code fences, where Markdown-looking examples are not links.
        in_fence = False
        for number, line in enumerate(content.splitlines(), 1):
            stripped = line.lstrip()
            if stripped.startswith("```") or stripped.startswith("~~~"):
                in_fence = not in_fence
                continue
            if in_fence:
                continue
            for match in LINK.finditer(line):
                raw = match.group(1).strip("<>")
                if not raw or raw.startswith("#") or raw.startswith("//") or SCHEMES.match(raw):
                    continue
                # This is a filesystem verifier: external/public URL checks
                # require an authorised separate publication review.
                target = urlsplit(raw)
                if target.scheme or target.netloc or not target.path:
                    continue
                decoded = unquote(target.path)
                if "\x00" in decoded:
                    issues.append(f"{relative}:{number}: unsafe NUL in link")
                    continue
                resolved = (path.parent / decoded).resolve()
                if not resolved.is_relative_to(root):
                    issues.append(f"{relative}:{number}: local link escapes repository: {raw}")
                elif not resolved.exists():
                    issues.append(f"{relative}:{number}: missing local target {raw}")
                else:
                    checked += 1
    return issues, checked


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=ROOT)
    parser.add_argument("--all", action="store_true", help="Audit every tracked Markdown file, including history")
    parser.add_argument("--paths", nargs="+", help="Explicit paths relative to --root (for targeted checks)")
    args = parser.parse_args()
    root = args.root.resolve()
    paths = ([root / item for item in args.paths] if args.paths
             else all_paths(root) if args.all else current_paths(root))
    failures, count = problems(root, paths)
    for issue in failures:
        print("Documentation link error:", issue)
    print(f"Checked {len(paths)} Markdown files and {count} existing relative targets; {len(failures)} defects.")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
