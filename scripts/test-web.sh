#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/../apps/web"
npm ci --legacy-peer-deps
npm audit --audit-level=high
npm run typecheck
npm run lint
npm run test
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api NEXT_PUBLIC_USE_MOCK_API=false OPFIN_ENABLE_DEMO_SHORTCUTS=false npm run build
