# Android release build request

Originally recorded: 18 September 2026. Last reviewed: 19 September 2026.

## Status

**A signed, upload-ready AAB has not been verified or delivered by this review. Merging the release workflow is not the same as generating a release.**

[PR #45](https://github.com/lynelk/OpFin/pull/45) was merged into `main` on 18 September 2026 at 18:11 UTC (21:11 East Africa Time), with merge commit `8a7caf79a369a215db1f5257505daffe301971f2`. Earlier instructions describing it as an unmerged draft are superseded.

The main application commit inspected during this review was `3d0038347161e94e56a80bc34ed82cf6ffad7524`. Its Android Gradle plugin configuration is 9.4.0. This is newer than the original application baseline `58962e2f32e5365fe1d8ca24b608c6b2e1c6b15b`; the earlier baseline's successful CI must not be treated as validation of a later signed build.

Two inspected signed-release attempts ended before any job ran:

| Attempt | Commit | Result |
| --- | --- | --- |
| [35362957600](https://github.com/lynelk/OpFin/actions/runs/35362957600) | `bf09dea8e32a9f23a3aeaf136b588077b1c74b6f` | `startup_failure`, as recorded in the original release investigation. |
| [35378627517](https://github.com/lynelk/OpFin/actions/runs/35378627517) | `aa9a38ebda49619c68fd8f327dd8ee2c881bb988` | `startup_failure`; the 19 September review confirmed zero jobs and zero retained artifacts. |

The repository's workflow-dispatch query returned zero runs during this review. No manually triggered signed build on `main` was therefore evidenced by that query. The current workflow does not automatically run a signed release on ordinary pushes to `main`.

The underlying startup rejection annotation was not exposed by the connected reads. **The presence and correctness of the signing secrets remain unverified, not confirmed missing.** Do not attribute either startup rejection to passwords, missing secrets, environment rules or action restrictions without the actual annotation. Changing the checkout step did not establish that the startup issue was resolved.

The configured application is `OpFin`, package `co.opfin.app`, version `1.0.0+17`. The release workflow checks for target SDK 36 or newer. No application features, branding, signing key, repository security policy or Play release track were changed in this documentation review.

## Release workflow changes already on main

The merged `.github/workflows/android-release.yml` is configured to:

- Build both a signed AAB for Google Play and a signed APK for direct device testing, with the same API configuration and store feature flags.
- Use the `google-play-production` environment and require the five inputs listed below; signing is not allowed to fall back to a debug key.
- Report the names of missing inputs without printing their values.
- Restore private signing material temporarily, reject an Android Debug certificate, and clean up private material after the job.
- Validate the AAB with integrity-checked Google bundletool 1.18.3; check signatures, package, version, non-debuggability and APK signer consistency.
- Retain explicitly listed binaries, checksums, source/version metadata and the public upload certificate for 14 days. The workflow does not publish to Google Play.

These are configuration findings, not proof that these steps have executed successfully. The original 18 September note recorded local component checks. Those checks were not repeated or independently verified in this 19 September documentation review, and are not Android build certification.

## Required protected configuration

In repository `lynelk/OpFin`, open **Settings > Environments > google-play-production > Environment secrets**. Configure the following exact names. Environment secrets become available only after the environment's protection rules pass.

| Secret name | Exact required content | How to establish it |
| --- | --- | --- |
| `ANDROID_KEYSTORE_BASE64` | Base64 encoding of the binary upload keystore containing the private upload key. Not a filename, path, public certificate, fingerprint or Google service-account JSON file. | Use the intended existing `.jks`/`.keystore` file. Do not silently replace a registered upload key. |
| `ANDROID_KEY_ALIAS` | The exact alias of the private-key entry in that keystore. | Obtain it from the existing key's custodian or inspect the keystore locally with `keytool -list -v`. There is no verified existing alias in the repository. |
| `ANDROID_KEYSTORE_PASSWORD` | The password that opens that keystore. | Retrieve it from the authorised key custodian/password manager. |
| `ANDROID_KEY_PASSWORD` | The password that unlocks the selected private key. | It may equal the keystore password. Both named secrets must still be set because the workflow checks both. |
| `OPFIN_API_BASE_URL` | The production HTTPS backend URL ending in `/api`. | Current repository CI uses `https://opfin-production.up.railway.app/api`. This identifies the repository-configured value; live production ownership, availability and the actual environment-secret value were not independently verified in this review. |

The API URL is public configuration, but the current workflow reads it from `secrets`, not `vars`. Creating only an environment variable with that name will not satisfy this version of the workflow.

Do not put the private keystore, its Base64 representation, signing passwords, GitHub tokens or Google account credentials in chat, source control, PR comments or screenshots. Base64 is an encoding, not encryption. A screenshot showing secret names only is sufficient to confirm that an owner has configured the fields; it does not prove their contents are correct.

### Existing key versus first-ever upload

If `co.opfin.app` already has a registered Google Play upload certificate, obtain the matching private upload keystore and passwords. The public certificate alone cannot sign an AAB. A lost upload key requires the Play Console upload-key reset process; generating a different key alone does not make an existing app accept it.

If this is the first-ever upload and no upload key has been registered, the owner must authorise initial upload-key creation. Generate the key on an owner-controlled development machine or approved secret-management system, retain an encrypted backup under company control, and populate the GitHub environment secrets. Do not generate or distribute a production private key in a chat attachment. A new alias such as `opfin-upload` is a proposed choice, not an existing verified alias.

The build needs the upload key. With Play App Signing, Google manages the separate app-signing key used for distributed installations. A Google login, Play Console password or service-account JSON is not a dependency of this build-only workflow.

### Secure command-line secret setup for the key custodian

These commands are for an authorised local machine with GitHub CLI and Python installed and authenticated to the correct GitHub account. Replace the keystore path with the existing approved upload keystore. They do not generate or replace a key and do not start a release.

```bash
# Do not enable shell tracing while handling secrets.
set +x
set -euo pipefail

# Send the encoded file directly to GitHub, without displaying it or saving a Base64 copy.
python3 -c 'import base64, pathlib, sys; sys.stdout.write(base64.b64encode(pathlib.Path(sys.argv[1]).read_bytes()).decode("ascii"))' \
  '/absolute/path/to/approved-upload-keystore.jks' \
  | gh secret set ANDROID_KEYSTORE_BASE64 --repo lynelk/OpFin --env google-play-production

# GitHub CLI prompts for each value; do not put passwords into command arguments.
gh secret set ANDROID_KEY_ALIAS --repo lynelk/OpFin --env google-play-production
gh secret set ANDROID_KEYSTORE_PASSWORD --repo lynelk/OpFin --env google-play-production
gh secret set ANDROID_KEY_PASSWORD --repo lynelk/OpFin --env google-play-production
gh secret set OPFIN_API_BASE_URL --repo lynelk/OpFin --env google-play-production

# List names and metadata only, not secret values.
gh secret list --repo lynelk/OpFin --env google-play-production
```

## Completing the build

1. **Resolve startup using evidence.** Open run `35378627517`, select its Summary and copy the complete startup error/Annotations text, or provide a screenshot without credentials. No jobs exist from which to retrieve a compilation log. The current connector exposes repository files and run results but does not provide environment-secret administration or this startup annotation.
2. **Check the permitted execution route.** In Settings > Actions > General, confirm that the current workflow's `actions/checkout@v4` and `actions/upload-artifact@v4` are permitted. Do not enable all actions indiscriminately. If the actual annotation reports a full-commit-SHA requirement, pin the approved actions in code rather than disabling the policy. If it reports an environment rule or approval requirement, have the authorised owner resolve that requirement without bypassing it.
3. **Use the existing protected environment.** Confirm that `main` is an allowed deployment branch for `google-play-production`. Retain required reviewers and have a permitted reviewer approve the run when GitHub requests it. Do not broaden access to all branches merely to get a build through.
4. **Supply the five inputs above.** If they are already configured, do not rotate or replace them unnecessarily. The first running preflight step reports missing input names; later checks establish validity.
5. **Check Play identity and version reuse.** Establish whether this is a first-ever upload. For an existing app, obtain the public upload-certificate SHA-256 fingerprint and the highest version code already used in Play Console, including testing tracks. Version code 17 must be unused and suitable for the intended update; otherwise increase the version code in source before building. Do not change `co.opfin.app` to work around an upload-key mismatch.
6. **Run from current main.** In Actions > Android signed release > Run workflow, select `main`. Alternatively, an authorised operator can use `gh workflow run android-release.yml --repo lynelk/OpFin --ref main`. This starts a build, not Play publication. Note the exact source commit and approve the environment if required.
7. **Require a complete successful run.** Analysis, tests, compilation, signing, bundle validation, identity/signature checks and artifact upload must succeed. Secret configuration alone is not a promise that all later build steps will pass. Investigate any actual compiler/toolchain failures separately.
8. **Retrieve and independently verify the output.** The artifact should be named `opfin-android-<commit>`. At the current source version its intended contents include `OpFin-1.0.0-17.aab`, `OpFin-1.0.0-17.apk`, `SHA256SUMS.txt`, `release-info.json`, `upload-certificate.pem` and instructions. These are intended filenames, not files produced by this review. Compare the upload-certificate fingerprint with Play Console. The current workflow checks consistency with its configured keystore; it does not independently query Google's registered certificate.

The APK is for direct device testing and may not update an installation signed with Google's different app-signing key. The AAB is the Play upload artifact. Store listing content, policy declarations and production rollout remain separate from generating these binaries.

## Non-secret information required from the owner

Provide: first-ever upload or existing Play app; whether an approved upload keystore exists; confirmation that the five GitHub environment secrets are set; the public upload-certificate SHA-256 fingerprint where one is already registered; the highest version code used, or confirmation that no build has ever been uploaded; and the startup error text/screenshot if the run is still rejected before any job starts.

Do not send the keystore or passwords. Existing GitHub access to this repository does not establish access to its protected signing credentials or Play Console state.

## References

Repository evidence is linked above. External platform guidance:

- [Android app signing and upload keys](https://developer.android.com/studio/publish/app-signing)
- [GitHub environment configuration and protection rules](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments)
- [GitHub Actions permissions and full-SHA policies](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/enabling-features-for-your-repository/managing-github-actions-settings-for-a-repository)
- [GitHub CLI secret set](https://cli.github.com/manual/gh_secret_set)
- [GitHub CLI workflow run](https://cli.github.com/manual/gh_workflow_run)
