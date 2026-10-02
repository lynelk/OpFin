#!/usr/bin/env python3
from pathlib import Path
import plistlib
import re

root = Path(__file__).resolve().parents[1]
api = (root/'apps/api/routes/api.php').read_text()
bootstrap = (root/'apps/api/bootstrap/app.php').read_text()
assert 'routes/account_deletion.php' not in bootstrap
assert not (root/'apps/api/routes/account_deletion.php').exists()
assert api.count("'/account/deletion-readiness'") == 1
assert api.count("'/account/data'") == 1
plist = plistlib.loads((root/'apps/client/ios/Runner/Info.plist').read_bytes())
assert plist['NSCameraUsageDescription'] and plist['NSPhotoLibraryUsageDescription']
privacy = plistlib.loads((root/'apps/client/ios/Runner/PrivacyInfo.xcprivacy').read_bytes())
assert len(privacy['NSPrivacyAccessedAPITypes']) == 2
project = (root/'apps/client/ios/Runner.xcodeproj/project.pbxproj').read_text()
assert 'PrivacyInfo.xcprivacy in Resources' in project
assert 'OPFIN_IOS_BUNDLE_ID=co.opfin.ci' not in (root/'.github/workflows/ci.yml').read_text()
release = (root/'apps/client/tool/build_release.sh').read_text()
assert 'channel=huawei_appgallery' in release and 'CI=false flutter build apk' in release
assert re.search(r'^version:\s*\S+\+\d+', (root/'apps/client/pubspec.yaml').read_text(), re.M)
print('PR142 route, iOS permission/manifest, identity and shared Huawei release contracts passed.')
