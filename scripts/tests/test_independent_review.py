import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('review_gate', Path(__file__).resolve().parents[1]/'verify-independent-review.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class ReviewGateTest(unittest.TestCase):
    def review(self, **changes):
        row = {'id': 1, 'user': {'login': 'independent-reviewer', 'type': 'User'}, 'author_association': 'COLLABORATOR',
               'state': 'APPROVED', 'commit_id': 'exact-head', 'submitted_at': '2026-10-02T00:00:00Z'}
        row.update(changes)
        return row

    def test_exact_independent_human_approval_is_required(self):
        self.assertEqual(['independent-reviewer'], module.independent_approvers([self.review()], 'author', 'exact-head'))
        self.assertEqual([], module.independent_approvers([self.review()], 'independent-reviewer', 'exact-head'))
        self.assertEqual([], module.independent_approvers([self.review(commit_id='old-head')], 'author', 'exact-head'))
        self.assertEqual([], module.independent_approvers([self.review(user={'login': 'bot[bot]', 'type': 'Bot'})], 'author', 'exact-head'))
        self.assertEqual([], module.independent_approvers([self.review(author_association='NONE')], 'author', 'exact-head'))

    def test_later_changes_requested_or_dismissal_invalidates_old_approval(self):
        for state in ['CHANGES_REQUESTED', 'DISMISSED']:
            later = self.review(id=2, state=state, submitted_at='2026-10-02T01:00:00Z')
            self.assertEqual([], module.independent_approvers([self.review(), later], 'author', 'exact-head'))

if __name__ == '__main__':
    unittest.main()
