#!/usr/bin/env bash
# Explicitly invoked, encrypted offsite logical backup. No local plaintext dump.
# Requires authorised connectivity to the existing PostgreSQL service and
# an independently controlled offsite object store, outside Railway.
set -euo pipefail
umask 077

required=(OPFIN_BACKUP_DATABASE_URL OPFIN_BACKUP_AGE_RECIPIENT OPFIN_OFFSITE_BUCKET)
for name in "${required[@]}"; do
  if [[ -z "${!name:-}" ]]; then
    printf 'Missing required backup configuration: %s\n' "$name" >&2
    exit 2
  fi
done
for tool in pg_dump age aws; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    printf 'Missing offsite-backup dependency: %s\n' "$tool" >&2
    exit 2
  fi
done
if [[ ! "${OPFIN_OFFSITE_BUCKET}" =~ ^[A-Za-z0-9._-]+$ ]]; then
  echo 'Invalid bucket identifier.' >&2
  exit 2
fi
if [[ ! "${OPFIN_BACKUP_AGE_RECIPIENT}" =~ ^age1[0-9a-z]+$ ]]; then
  echo 'The backup requires an age public encryption recipient, not a passphrase.' >&2
  exit 2
fi

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
key="opfin/postgresql/production/${timestamp}.dump.age"
destination="s3://${OPFIN_OFFSITE_BUCKET}/${key}"
aws_args=()
if [[ -n "${OPFIN_OFFSITE_S3_ENDPOINT:-}" ]]; then
  [[ "${OPFIN_OFFSITE_S3_ENDPOINT}" == https://* ]] || { echo 'Offsite S3 endpoint must use HTTPS.' >&2; exit 2; }
  aws_args+=(--endpoint-url "${OPFIN_OFFSITE_S3_ENDPOINT}")
fi

# Transport TLS must be configured by the connection URL/certificate policy.
# Do not log the URL: it may embed a database password.
pg_dump --dbname="${OPFIN_BACKUP_DATABASE_URL}" --format=custom --no-owner --no-acl |
  age --recipient "${OPFIN_BACKUP_AGE_RECIPIENT}" |
  aws "${aws_args[@]}" s3 cp - "${destination}" --only-show-errors

aws "${aws_args[@]}" s3api head-object --bucket "${OPFIN_OFFSITE_BUCKET}" --key "${key}" >/dev/null
echo "Encrypted offsite backup uploaded and existence checked: ${key}"
echo "Restore drill and financial-data integrity verification remain separate required evidence."
