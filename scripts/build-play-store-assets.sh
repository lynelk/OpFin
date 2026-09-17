#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v convert >/dev/null || { echo 'ImageMagick convert is required.' >&2; exit 1; }

source_logo='apps/client/assets/logo.png'
output_dir='distribution/google-play/assets'
mkdir -p "$output_dir"

# The canonical source includes a symbol and wordmark on the approved blue field.
# Crop the symbol for a legible small icon and retain comfortable safe space.
convert "$source_logo" -crop 2850x2450+2300+1550 +repage \
  -resize 370x320 -background '#0000D1' -gravity center -extent 512x512 \
  "$output_dir/opfin-play-icon-512.png"

convert -size 1024x500 xc:'#0000D1' \
  \( "$source_logo" -crop 2850x2450+2300+1550 +repage -resize 300x255 \) \
  -gravity west -geometry +70+0 -composite \
  -font DejaVu-Sans-Bold -fill white -pointsize 53 -gravity northwest \
  -annotate +430+150 'Money, clearer.' \
  -font DejaVu-Sans -fill '#38B6FF' -pointsize 30 \
  -annotate +432+230 'Responsible credit. Better choices.' \
  "$output_dir/opfin-feature-graphic-1024x500.png"

identify "$output_dir/opfin-play-icon-512.png" "$output_dir/opfin-feature-graphic-1024x500.png"
