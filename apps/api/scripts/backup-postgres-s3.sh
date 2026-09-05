#!/bin/sh
set -eu

umask 077

require() {
  name="$1"
  eval "value=\${$name:-}"
  if [ -z "$value" ]; then
    printf 'Required environment variable %s is not set.\n' "$name" >&2
    exit 2
  fi
}

for command in pg_dump age aws sha256sum; do
  command -v "$command" >/dev/null 2>&1 || {
    printf 'Required command is unavailable: %s\n' "$command" >&2
    exit 2
  }
done

require DATABASE_URL
require BACKUP_S3_BUCKET
require BACKUP_AGE_RECIPIENT

BACKUP_S3_PREFIX="${BACKUP_S3_PREFIX:-opfin/postgres}"
BACKUP_S3_ENDPOINT_URL="${BACKUP_S3_ENDPOINT_URL:-}"
OPFIN_RELEASE_SHA="${OPFIN_RELEASE_SHA:-unknown}"

now="$(date -u +%Y%m%dT%H%M%SZ)"
workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT INT TERM

dump="$workdir/opfin-$now.dump"
encrypted="$dump.age"
checksum="$encrypted.sha256"
metadata="$workdir/opfin-$now.metadata.json"
metadata_encrypted="$metadata.age"
object_prefix="s3://$BACKUP_S3_BUCKET/$BACKUP_S3_PREFIX/$now"

printf 'Creating PostgreSQL logical backup at %s UTC.\n' "$now"
pg_dump \
  --dbname="$DATABASE_URL" \
  --format=custom \
  --compress=9 \
  --no-owner \
  --no-privileges \
  --file="$dump"

cat >"$metadata" <<EOF
{
  "created_at_utc": "$now",
  "application": "OpFin",
  "release_sha": "$OPFIN_RELEASE_SHA",
  "format": "pg_dump-custom",
  "encrypted_with": "age"
}
EOF

age -r "$BACKUP_AGE_RECIPIENT" -o "$encrypted" "$dump"
age -r "$BACKUP_AGE_RECIPIENT" -o "$metadata_encrypted" "$metadata"
sha256sum "$encrypted" | awk '{print $1}' >"$checksum"

aws_args=""
if [ -n "$BACKUP_S3_ENDPOINT_URL" ]; then
  aws_args="--endpoint-url $BACKUP_S3_ENDPOINT_URL"
fi

# shellcheck disable=SC2086
aws $aws_args s3 cp "$encrypted" "$object_prefix/opfin.dump.age" --only-show-errors
# shellcheck disable=SC2086
aws $aws_args s3 cp "$checksum" "$object_prefix/opfin.dump.age.sha256" --only-show-errors
# shellcheck disable=SC2086
aws $aws_args s3 cp "$metadata_encrypted" "$object_prefix/metadata.json.age" --only-show-errors

printf 'Encrypted backup uploaded successfully: %s/opfin.dump.age\n' "$object_prefix"
