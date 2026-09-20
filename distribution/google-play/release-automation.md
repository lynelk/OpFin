# Secure Android release automation setup

The workflow `.github/workflows/android-release.yml` creates a signed AAB on a manual dispatch or an `android-v*` tag. It does not publish automatically; a release owner must validate the artifact and submit it through Play Console. This keeps first-release legal declarations and staged rollout under explicit human control.

## One-time setup

1. In GitHub, create the environment `google-play-production`.
2. Require approval from at least one release owner who did not author the release commit.
3. Restrict deployment branches/tags to `main` and `android-v*` release tags.
4. Add these environment secrets:
   - `ANDROID_KEYSTORE_BASE64`: base64 of the Play upload keystore, as one value.
   - `ANDROID_KEYSTORE_PASSWORD`.
   - `ANDROID_KEY_ALIAS`.
   - `ANDROID_KEY_PASSWORD`.
   - `OPFIN_API_BASE_URL`: the public production HTTPS URL ending in `/api`.
5. Enable Play App Signing and register the corresponding upload certificate.
6. Store an encrypted offline backup and recovery ownership record outside GitHub.

Generate the base64 value locally without copying the keystore into the repository:

```bash
base64 -w 0 opfin-upload-keystore.jks
```

On macOS use `base64 < opfin-upload-keystore.jks | tr -d '\n'`.

## Release procedure

1. Update `apps/client/pubspec.yaml`; every upload needs a higher build number.
2. Merge only after monorepo CI succeeds.
3. Create a signed tag such as `android-v1.0.0-build17`, or manually dispatch the workflow from the validated `main` SHA.
4. Approve the protected environment deployment.
5. Download the AAB and checksum from the workflow run; artifacts expire after 14 days.
6. Verify the checksum, complete the checklist, then upload to Internal testing in Play Console.
7. Promote only after pre-launch reports, policy declarations, UAT and rollout approval are complete.

The workflow reconstructs signing files with owner-only permissions, never prints their contents, and destroys them in an `always()` cleanup step. Do not add automatic production promotion until the Play Developer API service account has least-privilege track permissions and a separate approval gate.



## Approved action pins

The Android workflow uses these exact official-action commits:

```text
actions/checkout@11bd71901bbe5b1630ceea73d27597364c9af683
actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02
```

These correspond to checkout v4.2.2 and upload-artifact v4.6.2. If repository Actions settings allow only owner-hosted actions, an administrator must allow these two exact references under selected actions before the build can start. Do not enable all GitHub or third-party actions to resolve this restriction. Updating a pin requires reviewing the upstream release and updating both the workflow and the permitted reference.

The production environment's reviewers, branch restrictions and signing secrets remain required. Workflow success produces release files only; it does not submit Play declarations or promote a production track.
