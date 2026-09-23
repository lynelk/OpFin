# Secure Android release automation setup

Status: Controlled internal Android release procedure  
Updated: 23 September 2026  
Language: English (United Kingdom)

## Current release method: local build, Actions disabled

The owner instructed on 20 September 2026 that **GitHub Actions remain disabled**. Do not enable Actions, dispatch workflows or create a release tag to trigger a build. The workflow below is retained for reference; its presence is not authorisation to run it.

Play Console identifies the existing OpFin app as `org.rotaryo.opfin` under **Core-Synergies**. The production version observed on 20 September was `15 (1.0.0)`, and the production draft contained no new bundle. Pending approved privacy-policy and Data safety changes do not contain an app update. Check the highest version code across every track again before choosing a build number; source version `1.0.0+17` is not evidence that code 17 is unused.

Use an authorised release workstation with Flutter 3.47.1, JDK 17 and Android SDK platform 37. Keep the registered private upload keystore and its passwords on that workstation. A public certificate or GitHub secret name cannot sign a build, and GitHub does not return stored secret values.

The public upload-certificate SHA-256 fingerprint observed in Play Console on 20 September 2026 was:

```text
F8:99:9F:DD:B4:C2:41:07:EB:7F:8B:33:7C:7B:BE:9C:93:20:0A:B1:F6:E5:7E:81:FC:CD:E2:ED:29:F0:B2:87
```

Recheck this fingerprint before upload if an upload-key reset has occurred. It is distinct from the Play app-signing certificate used on user devices.

1. Check out the reviewed candidate commit and record its SHA. Run the applicable API, web, Flutter, security and deployment-contract checks locally and retain the results. Disabled Actions does not waive release validation.
2. Configure the ignored `apps/client/android/key.properties` with `storeFile` (absolute keystore path), `storePassword`, `keyAlias` and `keyPassword`. Restrict this file and the keystore to their owner; never commit or share their contents.
3. Confirm that the keystore's certificate matches the **upload key certificate** shown in Play Console. Do not replace the registered key or change the package name to resolve a mismatch. If the key is lost, its owner must use the official upload-key recovery process.
4. From `apps/client`, set `OPFIN_API_BASE_URL` to the verified production HTTPS API URL ending in `/api`, then run:

   ```bash
   bash tool/build_release.sh android
   CI=false flutter build apk --release --no-pub \
     "--dart-define=OPFIN_API_BASE_URL=${OPFIN_API_BASE_URL%/}" \
     --dart-define=OPFIN_APP_STORE_P2P_BORROWING_ENABLED=false
   ```

   The helper runs Flutter analysis and tests and requires production signing. Outputs are `build/app/outputs/bundle/release/app-release.aab` and `build/app/outputs/flutter-apk/app-release.apk`.
5. Use `jarsigner -verify -strict` with the upload keystore as the trust source, and `apksigner verify --verbose --print-certs` for the APK. Use the integrity-checked bundletool version below to validate the AAB and dump its manifest. Require package `org.rotaryo.opfin`, the intended unused version code, target SDK at least 36, a non-debuggable application and the registered upload certificate. Record SHA-256 checksums and the source commit with the validation results. Do not put passwords on the command line or in logs.
6. Follow `release-checklist.md`, upload the verified AAB to internal testing, review Play's pre-launch results and complete signed-candidate UAT. Update the existing production draft only after those checks pass. Review the track's country selection, release notes and rollout percentage before publication.

## Retained GitHub workflow reference

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

## Workflow release procedure (inactive while Actions is disabled)

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

These correspond to checkout v4.2.2 and upload-artifact v4.6.2. Actions remains disabled under the current owner instruction; do not change the repository setting to use these references. If the owner later explicitly changes that instruction, an administrator may allow these two exact references under selected actions. Do not enable all GitHub or third-party actions. Updating a pin requires reviewing the upstream release and updating both the workflow and the permitted reference.

The production environment's reviewers, branch restrictions and signing secrets remain required. Workflow success produces release files only; it does not submit Play declarations or promote a production track.
