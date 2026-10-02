#!/usr/bin/env python3
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
