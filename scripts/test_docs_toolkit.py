#!/usr/bin/env python3
"""Regression tests for documentation discovery, safety and API evidence."""
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

from docs_toolkit import (build, documented_routes, documents, export_api, historical,
                          link_errors, normalise_routes, normalise_uri)


class DocumentationTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.run_git('init', '-q')
        self.run_git('config', 'user.name', 'Documentation test')
        self.run_git('config', 'user.email', 'docs-test@example.invalid')
        self.write('README.md', '# OpFin\n\nA test reference.\n')
        self.run_git('add', '.')
        self.run_git('commit', '-qm', 'fixture')

    def tearDown(self):
        self.temp.cleanup()

    def run_git(self, *args):
        return subprocess.check_output(['git', *args], cwd=self.root, text=True).strip()

    def write(self, name, text):
        path = self.root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text, encoding='utf-8')
        return path

    def test_only_tracked_markdown(self):
        self.write('private.md', 'Not committed')
        self.write('.env', 'SECRET=value')
        self.assertEqual([d['path'] for d in documents(self.root)], ['README.md'])

    def test_historical_classification(self):
        for p in ('docs/releases/x.md', 'apps/api/docs/audit/x.md', 'docs/2026-09-18-review.md'):
            self.assertTrue(historical(p))
        self.assertFalse(historical('apps/api/docs/api/current-endpoints.md'))

    def test_ignore_dependencies(self):
        self.write('vendor/example.md', '# Dependency')
        self.run_git('add', '.')
        self.assertEqual(len(documents(self.root)), 1)

    def test_symlinks_are_excluded(self):
        (self.root / 'linked.md').symlink_to(self.root / 'README.md')
        self.run_git('add', '.')
        self.assertEqual(len(documents(self.root)), 1)

    def test_missing_links_reported(self):
        d = {'path': 'README.md', 'text': '[missing](not-here.md) [web](https://example.invalid)'}
        self.assertEqual(len(link_errors(self.root, d)), 1)

    def test_code_example_is_not_a_link(self):
        d = {'path': 'README.md', 'text': '```md\n[example](absent.md)\n```\n'}
        self.assertEqual(link_errors(self.root, d), [])

    def test_root_and_encoded_links(self):
        self.write('docs/my guide.md', '# Guide')
        d = {'path': 'README.md', 'text': '[ok](/docs/my%20guide.md#part)'}
        self.assertEqual(link_errors(self.root, d), [])

    def test_link_cannot_escape_root(self):
        d = {'path': 'README.md', 'text': '[bad](../../etc/passwd)'}
        self.assertEqual(len(link_errors(self.root, d)), 1)

    def test_documentation_cannot_inject_script(self):
        self.write('README.md', '# Test\n</script><script>alert(1)</script>')
        build(self.root, self.root / '.build/docs')
        page = (self.root / '.build/docs/index.html').read_text()
        self.assertNotIn('</script><script>alert', page)
        self.assertIn("script-src 'sha256-", page)
        self.assertNotIn('https://cdn.', page)

    def test_route_validation_and_scope(self):
        routes = normalise_routes([
            {'method': 'GET|HEAD', 'uri': 'api/profile', 'middleware': ['auth:sanctum']},
            {'method': 'GET', 'uri': 'api/demo/dashboard'},
            {'method': 'GET', 'uri': '/'}])
        self.assertEqual(len(routes), 1)
        self.assertEqual(routes[0]['method'], 'GET')
        self.assertEqual(routes[0]['middleware'], ['auth:sanctum'])

    def test_malformed_routes_fail(self):
        for raw in ({'oops': []}, [], [{'uri': 'api/profile'}], [{'method': 'TRACE', 'uri': 'api/x'}]):
            with self.assertRaises(ValueError):
                normalise_routes(raw)

    def test_duplicate_routes_fail(self):
        route = {'method': 'GET', 'uri': 'api/profile'}
        with self.assertRaises(ValueError):
            normalise_routes([route, route])

    def test_parameter_names_are_not_route_differences(self):
        self.assertEqual(normalise_uri('/api/loans/{loan}/repay'), normalise_uri('/api/loans/{loan_id}/repay'))
        self.assertEqual(normalise_uri('/api/options?channel=x'), '/api/options')
        self.assertEqual(normalise_uri('/api/options/{id?}'), '/api/options/{}')

    def test_curated_table_formats(self):
        text = '| GET | `/api/profile` |\n| A | `GET/POST /api/wallets` |\n'
        self.assertEqual(documented_routes(text), {('GET', '/api/profile'), ('GET', '/api/wallets'), ('POST', '/api/wallets')})

    def test_export_rejects_undocumented_registration_mismatch(self):
        for name in ('current-endpoints.md', 'API_QUICK_REFERENCE.md'):
            self.write('apps/api/docs/api/' + name, '| GET | `/api/absent` |')
        path = self.write('routes.json', json.dumps([{'method': 'GET', 'uri': 'api/profile'}]))
        with self.assertRaises(ValueError):
            export_api(self.root, path, self.root / '.build/api', 'testing')

    def test_export_tracks_coverage_without_inventing_schemas(self):
        for name in ('current-endpoints.md', 'API_QUICK_REFERENCE.md'):
            self.write('apps/api/docs/api/' + name, '| GET | `/api/profile` |')
        path = self.write('routes.json', json.dumps([{'method': 'GET', 'uri': 'api/profile'}, {'method': 'POST', 'uri': 'api/new'}]))
        result = export_api(self.root, path, self.root / '.build/api', 'testing')
        self.assertEqual(result['narrative_uncovered'], [['POST', '/api/new']])
        self.assertNotIn('openapi', result)
        self.assertEqual(result['registered_environment'], 'testing')

    def test_portal_rejects_wrong_export_commit(self):
        api = self.root / '.build/api'
        api.mkdir(parents=True)
        (api / 'api-routes.json').write_text('{"source_commit":"wrong"}')
        with self.assertRaises(ValueError):
            build(self.root, self.root / '.build/docs', api)

# Import the command-line module without executing its main function.
_spec = importlib.util.spec_from_file_location('drift', Path(__file__).with_name('verify-documentation-drift.py'))
_drift = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_drift)


class DriftTests(unittest.TestCase):
    def test_requests_and_middleware_require_docs(self):
        for path in ('apps/api/app/Http/Requests/Example.php', 'apps/api/app/Http/Middleware/Example.php', 'apps/api/config/services.php'):
            self.assertTrue(_drift.change_errors({path}, set()))

    def test_historical_update_cannot_satisfy_runtime_change(self):
        self.assertTrue(_drift.change_errors({'apps/api/app/Services/X.php'}, {'apps/api/docs/audit/old.md'}))

    def test_deleted_doc_cannot_satisfy_route_change(self):
        self.assertTrue(_drift.change_errors({'apps/api/routes/api.php', 'apps/api/docs/api/current-endpoints.md'}, set()))

    def test_relevant_api_update_passes(self):
        self.assertEqual(_drift.change_errors({'apps/api/app/Services/X.php'}, {'apps/api/docs/api/current-endpoints.md'}), [])

    def test_dependency_change_needs_setup_review(self):
        self.assertTrue(_drift.change_errors({'apps/api/composer.lock'}, {'docs/README.md'}))
        self.assertEqual(_drift.change_errors({'apps/api/composer.lock'}, {'docs/DEVELOPER_START_HERE.md'}), [])

    def test_unrelated_doc_does_not_pass_web_change(self):
        self.assertTrue(_drift.change_errors({'apps/web/src/page.tsx'}, {'docs/GLOSSARY.md'}))


if __name__ == '__main__':
    unittest.main()
