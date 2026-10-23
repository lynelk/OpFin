"""Fail-closed single-signer verification for Android release artifacts."""

import re


def verify_single_apk_signer(output: str, upload_fingerprint: str) -> bool:
    """Require exactly one APK signer and exact SHA-256 upload-certificate match.

    Android build-tools print either `Signer #1 ...` or `V2 Signer: ...`.
    A signer can appear once for each signature scheme, but every digest must
    match the same 32-byte certificate. Never accept a missing digest.
    """
    expected = upload_fingerprint.replace(":", "").strip().lower()
    if not re.fullmatch(r"[0-9a-f]{64}", expected):
        return False
    counts = re.findall(r"^Number of signers:\s*(\d+)\s*$", output, re.M)
    if len(counts) != 1 or counts[0] != "1":
        return False
    digests = re.findall(
        r"^(?:Signer #\d+|V\d+(?:\.\d+)? Signer):? certificate SHA-256 digest:\s*([0-9a-fA-F]{64})\s*$",
        output,
        re.M,
    )
    return bool(digests) and {digest.lower() for digest in digests} == {expected}
