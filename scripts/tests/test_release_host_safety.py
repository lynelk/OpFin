"""Release URL and background schema-gate regression tests."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class ReleaseSafetyTests(unittest.TestCase):
    def test_local_and_numeric_hosts_never_reach_flutter(self):
        urls = ['https://localhost./api', 'https://127.0.0.2/api',
                'https://10.0.0.1/api', 'https://192.168.1.1/api',
                'https://169.254.169.254/api', 'https://[::1]/api',
                'https://[fc00::1]/api', 'https://2130706433/api',
                'https://0x7f000001/api', 'https://127.1/api',
                'https://x.internal./api', 'https://example.local/api']
        with tempfile.TemporaryDirectory() as tmp:
            mock = Path(tmp) / 'flutter'
            mock.write_text('#!/bin/sh\necho FLUTTER_CALLED\n')
            mock.chmod(0o755)
            for url in urls:
                with self.subTest(url=url):
                    env = dict(os.environ, PATH=tmp + os.pathsep + os.environ['PATH'], OPFIN_API_BASE_URL=url)
                    result = subprocess.run(['bash', str(ROOT / 'apps/client/tool/build_release.sh'), 'android'],
                                            env=env, capture_output=True, text=True)
                    self.assertNotEqual(result.returncode, 0)
                    self.assertNotIn('FLUTTER_CALLED', result.stdout)

    def test_background_services_wait_before_consuming_or_scheduling(self):
        for role, command in [('worker', 'queue:work'), ('scheduler', 'schedule:work')]:
            text = (ROOT / f'apps/api/railway/start-{role}.sh').read_text()
            self.assertLess(text.index('deployment:wait-for-schema'), text.index(command))
            subprocess.run(['sh', '-n', str(ROOT / f'apps/api/railway/start-{role}.sh')], check=True)


if __name__ == '__main__':
    unittest.main()
