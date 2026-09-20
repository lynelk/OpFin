#!/usr/bin/env python3
"""Static release-contract checks that do not require signing secrets or a device."""

from pathlib import Path
import re
import sys
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
gradle = (ROOT / "apps/client/android/app/build.gradle").read_text(encoding="utf-8")
manifest_path = ROOT / "apps/client/android/app/src/main/AndroidManifest.xml"
manifest = ET.parse(manifest_path).getroot()
pubspec = (ROOT / "apps/client/pubspec.yaml").read_text(encoding="utf-8")
activity = (ROOT / "apps/client/android/app/src/main/kotlin/co/opfin/app/MainActivity.kt").read_text(encoding="utf-8")
workflow = (ROOT / ".github/workflows/android-release.yml").read_text(encoding="utf-8")

errors = []
def require(condition, message):
    if not condition:
        errors.append(message)

require('applicationId = "org.rotaryo.opfin"' in gradle, "Android applicationId must preserve existing Play listing org.rotaryo.opfin")
compile_match = re.search(r"compileSdk\s*=\s*(\d+)", gradle)
require(compile_match is not None and int(compile_match.group(1)) >= 37, "Android compileSdk must be at least 37")
target_match = re.search(r"targetSdk\s*=\s*(\d+)", gradle)
require(target_match is not None and int(target_match.group(1)) >= 36, "Android targetSdk must be at least 36")
require("CI=false" in workflow, "Signed release workflow must force production signing path")
require("org.rotaryo.opfin" in workflow, "Signed release verifier must require existing Play application ID")
require(re.search(r"^version:\s*[^\s+]+\+\d+\s*$", pubspec, re.M) is not None, "pubspec must declare numeric Android build/version code")
require("package co.opfin.app" in activity and "class MainActivity" in activity, "Kotlin launcher namespace/class contract is missing")

android = "{http://schemas.android.com/apk/res/android}"
permissions = {node.get(android + "name") for node in manifest.findall("uses-permission")}
require("android.permission.INTERNET" in permissions, "INTERNET permission is required")
require("android.permission.CAMERA" in permissions, "CAMERA permission is required for KYC capture")
for forbidden in ("android.permission.READ_SMS", "android.permission.READ_CONTACTS", "android.permission.READ_CALL_LOG",
                  "android.permission.READ_EXTERNAL_STORAGE", "android.permission.MANAGE_EXTERNAL_STORAGE"):
    require(forbidden not in permissions, f"Forbidden launch permission present: {forbidden}")
application = manifest.find("application")
require(application is not None, "Android application element is missing")
if application is not None:
    require(application.get(android + "allowBackup") == "false", "Android backups must remain disabled for sensitive app data")
    require(application.get(android + "usesCleartextTraffic") == "false", "Cleartext traffic must remain disabled")
    launcher = application.find("activity")
    require(launcher is not None and launcher.get(android + "name") == "co.opfin.app.MainActivity",
            "Manifest launcher must resolve explicitly to co.opfin.app.MainActivity")

if errors:
    print("Android release contract failed:")
    for error in errors:
        print(" - " + error)
    sys.exit(1)
print("Android release contract passed.")
