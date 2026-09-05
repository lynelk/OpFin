#!/usr/bin/env python3
"""Dependency-free regression tests; fixtures are synthetic and never live secrets."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

GUARD = Path(__file__).resolve().with_name('verify-repository-security.sh')


class RepositorySecurityTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        subprocess.run(['git', 'init', '-q', str(self.root)], check=True)

    def stage(self, name, content='synthetic fixture\n'):
        path = self.root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content)
        subprocess.run(['git', 'add', '--', name], cwd=self.root, check=True)
        return path

    def run_guard(self, env=None):
        return subprocess.run(['sh', str(GUARD)], cwd=self.root, capture_output=True, text=True, env=env)

    def test_empty_repository_passes(self):
        self.assertEqual(self.run_guard().returncode, 0)

    def test_script_and_tests_do_not_match_their_own_patterns(self):
        self.stage('scripts/verify-repository-security.sh', GUARD.read_text())
        self.stage('scripts/test-repository-security.py', Path(__file__).read_text())
        result = self.run_guard()
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_environment_suffixes_and_unusual_paths_are_rejected(self):
        names = ['.env', '.env.production', 'apps/api/.env.local', 'config.env', 'config.env.staging', 'path with spaces/.env.local', 'données.sql', 'line\nbreak.sqlite', 'database.sqlite3', 'cache.db', 'database.sqlite-wal', 'archive.ZIP', 'client.keystore']
        for name in names:
            with self.subTest(name=name):
                self.stage(name)
                result = self.run_guard()
                self.assertEqual(result.returncode, 1, result.stderr)
                subprocess.run(['git', 'rm', '-q', '-f', '--', name], cwd=self.root, check=True)

    def test_explicit_environment_templates_pass(self):
        for name in ['.env.example', '.env.sample', '.env.template', '.env.production.example', 'config.env.template']:
            self.stage(name)
        result = self.run_guard()
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_signatures_rejected_without_logging_values(self):
        signatures = [
            '-' * 5 + 'BEGIN PRIVATE KEY' + '-' * 5,
            '-' * 5 + 'BEGIN RSA PRIVATE KEY' + '-' * 5,
            'gh' + 'p_' + 'A' * 24,
            'github_' + 'pat_' + 'B' * 24,
            'sk_' + 'live_' + 'C' * 24,
            'AK' + 'IA' + 'D' * 16,
            'xox' + 'b-' + 'E' * 24,
            'APP_KEY=' + 'base64:' + 'F' * 43 + '=',
        ]
        for signature in signatures:
            with self.subTest(signature_type=signature[:4]):
                self.stage('embedded.txt', signature)
                result = self.run_guard()
                self.assertEqual(result.returncode, 1, result.stderr)
                self.assertNotIn(signature, result.stdout + result.stderr)

    def test_lockfiles_are_scanned(self):
        self.stage('package-lock.json', 'gh' + 'p_' + 'A' * 24)
        self.assertEqual(self.run_guard().returncode, 1)

    def test_staged_content_cannot_be_hidden_by_worktree_edit(self):
        path = self.stage('embedded.txt', 'gh' + 'p_' + 'A' * 24)
        path.write_text('now harmless but index is still sensitive')
        self.assertEqual(self.run_guard().returncode, 1)

    def test_git_errors_fail_closed(self):
        fake_bin = self.root / 'fake-bin'
        fake_bin.mkdir()
        fake_git = fake_bin / 'git'
        fake_git.write_text('#!/bin/sh\nexit 2\n')
        fake_git.chmod(0o755)
        env = dict(os.environ, PATH=str(fake_bin) + os.pathsep + os.environ['PATH'])
        result = self.run_guard(env)
        self.assertEqual(result.returncode, 2)
        self.assertIn('Git inspection error', result.stderr)


if __name__ == '__main__':
    unittest.main(verbosity=2)
