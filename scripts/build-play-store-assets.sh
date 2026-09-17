#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v convert >/dev/null || { echo 'ImageMagick convert is required.' >&2; exit 1; }
font='apps/client/assets/brand/InterVariable.ttf'
symbol='apps/client/assets/brand/opfin-symbol.png'
icon='apps/client/assets/brand/opfin-app-icon.png'
output_dir='distribution/google-play/assets'
test -s "$font" && test -s "$symbol" && test -s "$icon"
mkdir -p "$output_dir"
mapfile -t colours < <(python3 - <<'PY'
import json
c = json.load(open('brand/opfin.tokens.json'))['colours']
for key in ('indigo', 'apricot', 'ivory', 'periwinkle'):
    print(c[key])
PY
)
indigo=${colours[0]}; apricot=${colours[1]}; ivory=${colours[2]}; periwinkle=${colours[3]}
convert "$icon" -resize 512x512 -alpha on "PNG32:$output_dir/opfin-play-icon-512.png"
convert -size 1024x500 "xc:$ivory" \
  -fill none -stroke "$periwinkle" -strokewidth 96 \
  -draw "path 'M 1120,570 L 1120,258 Q 1120,92 954,92 L 926,92 Q 760,92 760,258 L 760,570'" \
  -stroke "$apricot" -strokewidth 76 \
  -draw "path 'M 1140,570 L 1140,286 Q 1140,180 1034,180 L 988,180 Q 882,180 882,286 L 882,570'" \
  \( "$symbol" -resize 60x66 \) -gravity northwest -geometry +72+56 -composite \
  -stroke none -fill "$indigo" -font "$font" -weight 700 -pointsize 44 \
  -annotate +154+110 'OpFin' \
  -pointsize 68 -annotate +72+264 'Your next step,' -annotate +72+348 'clearer.' \
  -stroke "$apricot" -strokewidth 6 -draw 'line 74,398 144,398' \
  -alpha off "PNG24:$output_dir/opfin-feature-graphic-1024x500.png"
python3 - <<'PY'
import hashlib, json
from pathlib import Path
path = Path('brand/asset-manifest.json')
manifest = json.loads(path.read_text())
for name in ('opfin-play-icon-512.png', 'opfin-feature-graphic-1024x500.png'):
    asset = Path('distribution/google-play/assets') / name
    manifest['files'][str(asset)] = hashlib.sha256(asset.read_bytes()).hexdigest()
path.write_text(json.dumps(manifest, indent=2) + '\n')
PY
identify "$output_dir/opfin-play-icon-512.png" "$output_dir/opfin-feature-graphic-1024x500.png"
python3 scripts/verify-security-controls.py
