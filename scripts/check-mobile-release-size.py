#!/usr/bin/env python3
import argparse
import os
import sys
from pathlib import Path

MIB = 1024 * 1024

def tree_size(path: Path) -> int:
    return sum(item.stat().st_size for item in path.rglob('*') if item.is_file())

def check_file(label: str, path: Path, limit: int, errors: list[str]) -> None:
    if not path.is_file():
        errors.append(f'{label} artefact is missing: {path}')
        return
    size = path.stat().st_size
    print(f'{label}: {size / MIB:.2f} MiB (limit {limit / MIB:.2f} MiB)')
    if size > limit:
        errors.append(f'{label} is {size / MIB:.2f} MiB, above {limit / MIB:.2f} MiB')

def check_dir(label: str, path: Path, limit: int, errors: list[str]) -> None:
    if not path.is_dir():
        errors.append(f'{label} artefact is missing: {path}')
        return
    size = tree_size(path)
    print(f'{label}: {size / MIB:.2f} MiB uncompressed (limit {limit / MIB:.2f} MiB)')
    if size > limit:
        errors.append(
            f'{label} is {size / MIB:.2f} MiB uncompressed, above {limit / MIB:.2f} MiB'
        )

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--android-aab')
    parser.add_argument('--android-apk')
    parser.add_argument('--ios-app')
    args = parser.parse_args()

    errors: list[str] = []
    if args.android_aab:
        check_file(
            'Android App Bundle',
            Path(args.android_aab),
            int(os.getenv('OPFIN_MAX_ANDROID_AAB_BYTES', 30 * MIB)),
            errors,
        )
    if args.android_apk:
        check_file(
            'Universal Android APK',
            Path(args.android_apk),
            int(os.getenv('OPFIN_MAX_ANDROID_APK_BYTES', 60 * MIB)),
            errors,
        )
    if args.ios_app:
        check_dir(
            'iOS Runner.app',
            Path(args.ios_app),
            int(os.getenv('OPFIN_MAX_IOS_APP_BYTES', 120 * MIB)),
            errors,
        )

    if not any([args.android_aab, args.android_apk, args.ios_app]):
        parser.error('provide at least one mobile release artefact')

    if errors:
        print('Mobile release size gate FAILED:')
        for error in errors:
            print(f' - {error}')
        return 1

    print('Mobile release size gate passed.')
    return 0

if __name__ == '__main__':
    sys.exit(main())
