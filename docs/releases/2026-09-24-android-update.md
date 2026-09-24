# Android 1.0.1 (19) candidate

Status: Candidate source prepared; signed build and acceptance pending  
Updated: 24 September 2026  
Language: English (United Kingdom)

## Source and scope

Candidate branch: `codex/android-update-20260924`, based on main commit `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f`. Record the final full candidate commit when building; do not use a moving branch as the release identity.

The candidate includes the newer main-branch mobile experience, brand assets, Financial Spaces and authentication changes. This preparation increments the version from `1.0.0+18` to `1.0.1+19`, restores the canonical Financial Compass heading and restricts Android device location to approximate foreground access. Place search and manual place entry remain available for more specific locations. iOS task-specific precise location behaviour is unchanged.

`ACCESS_FINE_LOCATION` is prohibited for personal-loan apps under the [Google Play financial-services policy](https://support.google.com/googleplay/android-developer/answer/9876821?hl=en). The restriction applies to this Android package, including its non-lending screens. Both Dart and native Android enforce approximate requests. Camera, autofocus, location and network-location hardware are optional for installation. This avoids those hardware filters; it does not establish support for every device model. Review the uploaded bundle's device-catalogue comparison and test representative previously supported models.

Package identity remains `org.rotaryo.opfin`. App-store P2P borrowing stays disabled. No financial-control, provider-finality or production activation approval is conferred by this release record.

## Play state observed on 24 September

| Track | Observed state |
| --- | --- |
| Production | Build 18 already available on Google Play in a staged rollout at 10%, covering 3 of 177 countries; this preparation did not start or expand that rollout |
| Internal testing | Build 18 published to OpFin Test Group; available to internal testers |
| Next candidate | Build 19 was unused in the observed bundle list; check all tracks again immediately before upload |

The internal test group contains `coresynergiesug@gmail.com` and `lynelk@gmail.com`. Testers can accept the invitation on an Android device using [the Play opt-in link](https://play.google.com/apps/internaltest/4701606297247902850).

## Validation and remaining gates

Passed during source preparation:

- Android release-contract guard, including rejection checks for precise permission, its SDK-23 variant and required location hardware;
- security-controls guard;
- brand asset synchronisation check;
- documentation drift check; and
- Git whitespace check.

The added Flutter tests cover Android precision restriction, iOS precision preservation and permission-denied recovery. They have not yet run: installing the Flutter validation runtime was blocked by network approval cancellation before its Dart SDK became available. Android and iOS compilation, Windows helper execution, the merged AAB manifest, signer verification and physical-device acceptance remain pending.

The source also records Flutter 3.47.1's standard analysis-options migration for build/platform directories, avoiding an automatic tracked-file change during release preparation. Application and test Dart sources remain analysed.

Main's inherited financial-control review findings also require the applicable review and acceptance; a passing mobile build must not be treated as closure. Keep this candidate in review until required checks are evidenced. The current listing, Data safety declaration, screenshots, pre-launch results and release notes must match the actual signed candidate before production promotion.

## Local Windows build

GitHub Actions remains disabled. Use a clean worktree at the reviewed full commit with Flutter 3.47.1 and the project's compatible Java/Android toolchain. Preserve the original Windows checkout and its uncommitted changes. Copy the existing ignored `android/key.properties` into the new worktree locally; it must reference the existing upload keystore by absolute path. Never include either file or a password in a PR, build log or chat.

From the candidate's `apps/client` directory:

```powershell
& .\tool\build_release.ps1 -ExpectedSourceCommit '<reviewed-full-40-character-commit>'
```

The helper runs analysis/tests, requires local release signing, validates the AAB with integrity-checked bundletool, rejects restricted permissions in the merged manifest, checks optional hardware and verifies the public upload-certificate fingerprint. It writes the AAB, manifest, SHA-256 checksum and source record to `build/release-delivery` without uploading anything.

The default expected upload certificate is the one shown in the owner's keystore evidence, ending `7E:BE:A3:58`. Confirm it against Play's current **upload key certificate**, especially after any reset. A mismatch must stop distribution; do not replace the key or package ID to bypass it.

Upload the verified candidate to internal testing, test on both nominated accounts, compare device support with build 18, and complete the existing [release checklist](../../distribution/google-play/release-checklist.md) before submitting a production update. No build 19 binary has been built, signed, uploaded or published during this source preparation.
