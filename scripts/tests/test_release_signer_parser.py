"""Signing-scheme-specific apksigner output must never bypass certificate matching."""

from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "apps/client"))
from tool.verify_release_signer import verify_single_apk_signer  # noqa: E402

FINGERPRINT = "0123456789abcdef" * 4
OTHER = "fedcba9876543210" * 4


class ReleaseSignerTests(unittest.TestCase):
    def test_v2_apksigner_output_matches_exact_upload_cert(self):
        output = f"Verified using v2 scheme: true\nNumber of signers: 1\nV2 Signer: certificate SHA-256 digest: {FINGERPRINT}\n"
        self.assertTrue(verify_single_apk_signer(output, FINGERPRINT))
        self.assertFalse(verify_single_apk_signer(output, OTHER))

    def test_legacy_and_multiple_schemes_same_cert(self):
        lines = (f"Number of signers: 1\nSigner #1 certificate SHA-256 digest: {FINGERPRINT}\n"
                 f"V3 Signer: certificate SHA-256 digest: {FINGERPRINT}\n")
        self.assertTrue(verify_single_apk_signer(lines, FINGERPRINT.upper()))

    def test_missing_multiple_or_conflicting_signers_rejected(self):
        samples = [
            "Number of signers: 1\n",
            f"Number of signers: 2\nV2 Signer: certificate SHA-256 digest: {FINGERPRINT}\n",
            f"Number of signers: 1\nV2 Signer: certificate SHA-256 digest: {FINGERPRINT}\nV3 Signer: certificate SHA-256 digest: {OTHER}\n",
            f"V2 Signer: certificate SHA-256 digest: {FINGERPRINT}\n",
            f"Number of signers: 1\nV2 Signer: certificate SHA-256 digest: {OTHER}\n",
            f"Number of signers: 1\nSigner #1 certificate SHA-256 digest: not-a-digest\n",
        ]
        for item in samples:
            with self.subTest(output=item):
                self.assertFalse(verify_single_apk_signer(item, FINGERPRINT))


if __name__ == "__main__":
    unittest.main()
