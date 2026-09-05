"""Local, dependency-free checks for deployment boundaries and release commands."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class DeploymentContractTests(unittest.TestCase):
    def test_service_boundaries(self):
        for role in ('api', 'web', 'worker', 'scheduler'):
            with self.subTest(role=role):
                cfg = json.loads((ROOT / 'infrastructure/railway' / f'{role}.json').read_text())
                self.assertEqual(cfg['build']['builder'], 'RAILPACK')
                self.assertEqual(cfg['deploy']['numReplicas'], 1)
                self.assertFalse(cfg['deploy']['sleepApplication'])
                if role in ('worker', 'scheduler'):
                    self.assertEqual(cfg['deploy']['restartPolicyType'], 'ALWAYS')
                    self.assertNotIn('healthcheckPath', cfg['deploy'])
                    self.assertNotIn('preDeployCommand', cfg['deploy'])
                if role == 'api':
                    self.assertEqual(cfg['deploy']['preDeployCommand'], ['sh railway/pre-deploy.sh'])

    def test_startup_preserves_runtime_cache(self):
        for script in (ROOT / 'apps/api/railway').glob('start-*.sh'):
            with self.subTest(script=script.name):
                subprocess.run(['sh', '-n', str(script)], check=True)
                text = script.read_text()
                self.assertNotIn('optimize:clear', text)
                self.assertNotIn('cache:clear', text)
                self.assertNotIn('migrate', text)
                self.assertIn('exec ', text)

    def test_web_server_is_not_debugging_requests(self):
        text = (ROOT / 'apps/api/Caddyfile').read_text()
        self.assertIn('level WARN', text)
        self.assertNotIn('DEBUG', text)
        self.assertIn('root * /app/public', text)
        self.assertIn('num_threads 4', text)

    def release(self, target, url=None):
        with tempfile.TemporaryDirectory() as tmp:
            mock = Path(tmp) / 'flutter'
            mock.write_text('#!/bin/sh\nprintf "CI=%s|%s\\n" "${CI:-}" "$*"\n')
            mock.chmod(0o755)
            env = dict(os.environ, PATH=tmp + os.pathsep + os.environ['PATH'], CI='true')
            env.pop('OPFIN_API_BASE_URL', None)
            if url is not None:
                env['OPFIN_API_BASE_URL'] = url
            return subprocess.run(['bash', str(ROOT / 'apps/client/tool/build_release.sh'), target], env=env, capture_output=True, text=True)

    def test_release_requires_real_api_url(self):
        for url in (None, 'http://example.com/api', 'https://localhost/api', 'https://example.com', 'https://u:p@example.com/api', 'https://api.railway.internal/api', 'https://example.com/api?token=secret'):
            with self.subTest(url=url):
                result = self.release('android', url)
                self.assertNotEqual(result.returncode, 0)
                self.assertNotIn('build appbundle', result.stdout)

    def test_android_cannot_use_ci_debug_signing(self):
        result = self.release('android', 'https://example.com/api/')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('CI=false|build appbundle --release --no-pub', result.stdout)
        self.assertIn('--dart-define=OPFIN_API_BASE_URL=https://example.com/api ', result.stdout)
        self.assertIn('--dart-define=OPFIN_APP_STORE_P2P_BORROWING_ENABLED=false', result.stdout)

    def test_ios_requires_signed_ipa(self):
        result = self.release('ios', 'https://example.com/api')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('build ipa --release --no-pub', result.stdout)
        self.assertNotIn('--no-codesign', result.stdout)

    def test_unknown_target_fails(self):
        self.assertEqual(self.release('unknown', 'https://example.com/api').returncode, 2)


if __name__ == '__main__':
    unittest.main()
