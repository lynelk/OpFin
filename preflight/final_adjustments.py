from pathlib import Path

for rel in ['apps/api/docs/api/API_QUICK_REFERENCE.md', 'apps/api/docs/api/current-endpoints.md', 'apps/api/docs/api/frontend-backend-contract.md']:
    p = Path(rel)
    p.write_text(p.read_text().rstrip() + '\n')

p = Path('apps/web/src/lib/api/account.ts')
s = p.read_text()
s = s.replace('!Array.isArray(data.data_categories)', '!object(data.data_deletion) || !Array.isArray(data.data_deletion.available_categories)')
s = s.replace('data.data_categories.map((item: unknown)', 'data.data_deletion.available_categories.map((item: unknown)')
s = s.replace('}), retained_record_categories: strings(data.retained_record_categories)\n  };', '}), retained_record_categories: strings(data.data_deletion.retained_record_categories)\n  };')
p.write_text(s)
p = Path('apps/web/src/lib/api/account.test.ts')
s = p.read_text().replace('active_obligations: [blocker], data_categories: []', 'active_obligations: [blocker], data_deletion: { available_categories: [] }')
s = s.replace('data_categories: [{ code: "location_context", label: "Saved location", description: "Optional" }], retained_record_categories: []', 'data_deletion: { available_categories: [{ code: "location_context", label: "Saved location", description: "Optional" }], retained_record_categories: [] }')
p.write_text(s)

Path('scripts/verify-independent-review.py').write_text(r'''#!/usr/bin/env python3
"""Require a current authorised human review, never a bot or stale approval."""
import argparse
import json
from pathlib import Path


def independent_approvers(reviews, author, head):
    latest = {}
    for review in sorted(reviews, key=lambda row: (row.get('submitted_at') or '', row.get('id') or 0)):
        user = review.get('user') or {}
        login = user.get('login') or ''
        if review.get('state') in {'APPROVED', 'CHANGES_REQUESTED', 'DISMISSED'} and login:
            latest[login] = review
    return sorted(login for login, review in latest.items()
                  if login.lower() != author.lower()
                  and (review.get('user') or {}).get('type') == 'User'
                  and not login.lower().endswith('[bot]')
                  and review.get('author_association') in {'OWNER', 'MEMBER', 'COLLABORATOR'}
                  and review.get('state') == 'APPROVED'
                  and review.get('commit_id') == head)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--reviews', type=Path, required=True)
    parser.add_argument('--author', required=True)
    parser.add_argument('--head', required=True)
    args = parser.parse_args()
    pages = json.loads(args.reviews.read_text())
    reviews = [row for page in pages for row in page] if pages and isinstance(pages[0], list) else pages
    accepted = independent_approvers(reviews, args.author, args.head)
    if not accepted:
        raise SystemExit('Independent financial-control approval required: an authorised human other than the PR author must approve this exact head. Bots, dismissed and stale approvals do not qualify.')
    print('Independent exact-head approval: ' + ', '.join(accepted))


if __name__ == '__main__':
    main()
''')
Path('scripts/tests/test_independent_review.py').write_text(r'''import importlib.util
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
''')
p = Path('.github/workflows/ci.yml')
s = p.read_text()
start = s.index('          approvals="$(gh api ')
end = s.index('          echo "Independent financial-control approval confirmed for PR #$pr."', start)
s = s[:start] + '''          head="$(gh api "/repos/$GITHUB_REPOSITORY/pulls/$pr" --jq '.head.sha')"
          gh api --paginate --slurp "/repos/$GITHUB_REPOSITORY/pulls/$pr/reviews?per_page=100" > "$RUNNER_TEMP/opfin-independent-reviews.json"
          python3 scripts/tests/test_independent_review.py
          python3 scripts/verify-independent-review.py --reviews "$RUNNER_TEMP/opfin-independent-reviews.json" --author "$author" --head "$head"
''' + s[end:]
p.write_text(s)
print('Readiness matches the actual API schema; independent approval requires the exact candidate and a real authorised human.')
