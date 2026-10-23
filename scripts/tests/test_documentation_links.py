"""Small isolated regression suite for the local Markdown link verifier."""
from __future__ import annotations
import importlib.util
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "verify-documentation-links.py"
SPEC = importlib.util.spec_from_file_location("opfin_link_checker", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class DocumentationLinkTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / "docs").mkdir()
        (self.root / "docs/guide.md").write_text("# Guide\n", encoding="utf-8")

    def check(self, markdown: str):
        path = self.root / "README.md"
        path.write_text(markdown, encoding="utf-8")
        return MODULE.problems(self.root, [path])

    def test_valid_internal_and_external_links(self):
        issues, count = self.check(
            "# Entry\n[Guide](docs/guide.md#chapter)\n"
            "[Web](https://example.invalid/docs)\n[Heading](#anchor)\n"
        )
        self.assertEqual(issues, [])
        self.assertEqual(count, 1)

    def test_missing_local_target_fails(self):
        issues, _ = self.check("# Entry\n[Missing](docs/not-here.md)\n")
        self.assertEqual(len(issues), 1)
        self.assertIn("missing local target", issues[0])

    def test_escaped_outside_root_fails(self):
        issues, _ = self.check("# Entry\n[Outside](../outside.md)\n")
        self.assertEqual(len(issues), 1)
        self.assertIn("escapes repository", issues[0])

    def test_code_fences_are_not_mistaken_for_links(self):
        issues, _ = self.check("# Entry\n```markdown\n[Fake](missing.md)\n```\n")
        self.assertEqual(issues, [])

    def test_missing_publication_document_fails(self):
        issues, _ = MODULE.problems(self.root, [self.root / "not-here.md"])
        self.assertEqual(len(issues), 1)


if __name__ == "__main__":
    unittest.main()
