# Independently controlled encrypted logical backup

**Tool:** `scripts/offsite-postgres-backup.sh`. **Status:** Prepared, not executed against production. No offsite destination/keys were provided. This script is opt-in operations functionality and **not a pre-deploy test blocker**.

## Requirements

- Execute **only** on an authorised hardened machine with private network connectivity to the existing production PostgreSQL service, compatible `pg_dump`, `age` and AWS-compatible `aws` CLI.
- An independently controlled, private object-storage bucket **outside Railway** with least-privilege write credentials, lifecycle retention and approved country/data-residency policy.
- Set the following in the runner's secret manager, never in Git: `OPFIN_BACKUP_DATABASE_URL` (TLS connection URL), `OPFIN_BACKUP_AGE_RECIPIENT` (age public encryption recipient), `OPFIN_OFFSITE_BUCKET`, optional `OPFIN_OFFSITE_S3_ENDPOINT` (HTTPS only). AWS credentials use supported secure CLI environment/provider.
- The script streams `pg_dump` custom format directly through age public-key encryption to the offsite bucket, with no plaintext file saved. The CLI checks object existence; this is **not** a restore test or guarantee of integrity.
- For restore drills, retrieve to an isolated secure machine, decrypt with the privately protected matching age identity, run `pg_restore` against **a disposable isolated database**, and verify migration/ledger/payment integrity. Never automatically connect workers or provider callbacks to the restored copy.
- Configure retention, offsite access alerts and cadence in the independent backup system; none is claimed to have been provisioned by this code.

Do not pass real DATABASE_URL/credentials in GitHub PRs, workflow logs or issue comments. Do not create a new Railway bucket or a public PostgreSQL port to run this script.
