# Android release build request — 18 September 2026

## Status

**No signed APK or AAB has been produced by this release attempt. Do not describe this as a completed release.**

The application source is main commit `58962e2f32e5365fe1d8ca24b608c6b2e1c6b15b`. Its normal CI run `35332143901` passed, but the CI workflow did not retain Android build artifacts. Normal CI also permits debug signing; a successful release-mode compilation is not evidence of a Google Play upload-ready bundle.

Release workflow commit `bf09dea8e32a9f23a3aeaf136b588077b1c74b6f` triggered [Android signed release run 35362957600](https://github.com/lynelk/OpFin/actions/runs/35362957600). GitHub returned `startup_failure`, with no jobs and no commit check runs. The underlying rejection annotation was not available through the connected API actions. This occurred before the protected-configuration check, signing or compilation. The existence and correctness of the signing secrets have therefore **not** been verified. Do not claim that missing secrets caused this particular failure.

The application version remains `1.0.0+17`; package ID is `co.opfin.app`; Android target SDK is 36. No application features, branding, main-branch code or production services were changed. Nothing was uploaded to Google Play.

## Release workflow changes

- Produce both a signed AAB for Google Play and a signed APK for direct device testing, using the same production API configuration and existing store feature flags.
- Keep the `google-play-production` environment and require all existing signing inputs. There is no debug-key or unsigned fallback.
- Fail early with the names of missing configuration inputs, without printing their values.
- Store temporary private signing material outside the checkout, escape Java Properties values safely, and remove private material after the run.
- Use integrity-checked Google bundletool 1.18.3 to validate the AAB and read its manifest. APK-only tools are no longer used on the AAB.
- Verify the bundle signature against the configured upload certificate; verify APK signature, signer fingerprint, package, version and non-debuggability.
- Record source commit, workflow run, version, certificate fingerprint and SHA-256 checksums. Upload only explicitly listed build outputs and public metadata, never the private keystore or passwords.

Local verification covered YAML parsing, every shell step, embedded Python syntax, trusted self-signed JAR verification, missing-secret rejection, metadata packaging fixtures, and rejection of incorrect package, version, debuggability, target SDK and APK signer. These are workflow component checks, **not an Android build or end-to-end certification**. Temporary test keys and synthetic binary fixtures were deleted.

## Required protected configuration

Repository settings → Environments → `google-play-production` must provide:

| Secret | Purpose |
| --- | --- |
| `ANDROID_KEYSTORE_BASE64` | Base64 of the intended existing upload keystore. |
| `ANDROID_KEY_ALIAS` | Alias of the upload key in that keystore. |
| `ANDROID_KEY_PASSWORD` | Password for the upload key. |
| `ANDROID_KEYSTORE_PASSWORD` | Password for the keystore. |
| `OPFIN_API_BASE_URL` | The approved public HTTPS production API URL ending in `/api`. |

Do not put private keys or passwords in issues, pull requests, chat messages or repository files. If an upload certificate is already registered in Google Play Console, use its matching existing upload key rather than generating an unrelated replacement. Preserve environment approvals and branch restrictions.

## Completing the build

1. Open the failed run and inspect GitHub's startup rejection annotation. Resolve the reported issue without disabling signing or environment protections. No specific rejection cause has yet been confirmed.
2. Confirm the protected environment configuration above, including whether this release branch is authorised to use it.
3. Re-run the release on `release/android-1.0.0-17-20260918`, or review and merge the workflow correction and manually run `Android signed release` on main. A merge to main alone does not trigger the signed-release workflow.
4. Require successful signing, bundle validation, manifest/signature checks and artifact upload.
5. Download the `opfin-android-<commit>` artifact. For the current application version it should contain `OpFin-1.0.0-17.aab`, `OpFin-1.0.0-17.apk`, `SHA256SUMS.txt`, `release-info.json`, a public upload certificate and instructions. These filenames describe intended outputs, not files already produced.
6. Confirm that version code 17 is unused for this application in Play Console. If it has already been used, increment the app version code and build again.
7. Compare the public upload-certificate fingerprint with Play Console. Upload the AAB to the intended testing/release track. An APK signed with the upload key is for direct testing and may not update an installation signed by Google's different app-signing key.

The workflow is build-only: it does not publish to Google Play, change a release track or roll out an app.

## References

- [Android app signing](https://developer.android.com/studio/publish/app-signing)
- [Google bundletool](https://developer.android.com/tools/bundletool)
- [Verified bundletool release](https://github.com/google/bundletool/releases/tag/1.18.3)
