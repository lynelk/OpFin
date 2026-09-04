#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/../apps/client"
flutter pub get
flutter analyze
flutter test
