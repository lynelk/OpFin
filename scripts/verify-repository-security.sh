#!/bin/sh
set -eu

fail=0

report() {
  printf '%s\n' "$1" >&2
  fail=1
}

# Production repositories should contain source and reproducible metadata, not
# local environments, private keys, database dumps, or ad-hoc release archives.
for path in $(git ls-files); do
  case "$path" in
    *.env)
      case "$path" in
        *.env.example|*.env.sample|*.env.template) ;;
        *) report "Tracked environment file is not allowed: $path" ;;
      esac
      ;;
    *.pem|*.key|*.p12|*.pfx|*.jks|*.keystore)
      report "Tracked private key/keystore material is not allowed: $path"
      ;;
    *.sql|*.dump|*.backup)
      report "Tracked database dump is not allowed: $path"
      ;;
    *.zip|*.tar|*.tar.gz|*.tgz|*.7z)
      report "Tracked archive is not allowed; use release artifacts instead: $path"
      ;;
  esac
done

scan_pattern() {
  label="$1"
  pattern="$2"
  matches="$(git grep -n -I -E "$pattern" -- . ':!**/*.lock' ':!**/package-lock.json' ':!**/pubspec.lock' 2>/dev/null || true)"
  if [ -n "$matches" ]; then
    printf 'Potential %s found in tracked source:\n%s\n' "$label" "$matches" >&2
    fail=1
  fi
}

scan_pattern 'private key' '-----BEGIN ([A-Z0-9]+ )?PRIVATE KEY-----'
scan_pattern 'GitHub classic token' 'gh[pousr]_[A-Za-z0-9]{20,}'
scan_pattern 'GitHub fine-grained token' 'github_pat_[A-Za-z0-9_]{20,}'
scan_pattern 'Stripe-style live secret' 'sk_live_[A-Za-z0-9]{16,}'
scan_pattern 'AWS access key' 'AKIA[0-9A-Z]{16}'
scan_pattern 'Slack token' 'xox[baprs]-[A-Za-z0-9-]{10,}'
scan_pattern 'non-placeholder Laravel application key' 'APP_KEY=base64:[A-Za-z0-9+/]{40,}={0,2}'

if [ "$fail" -ne 0 ]; then
  printf '\nRepository security verification failed. Remove the material from the current tree and rotate any credential that was exposed.\n' >&2
  exit 1
fi

printf 'Repository security verification passed.\n'
