import fs from 'node:fs';
import { spawnSync } from 'node:child_process';

const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
const result = spawnSync(npm, ['audit', '--json', '--audit-level=high'], {
  encoding: 'utf8',
  stdio: ['ignore', 'pipe', 'pipe'],
});

let report;
try {
  report = JSON.parse(result.stdout || '{}');
} catch {
  process.stderr.write(result.stdout || '');
  process.stderr.write(result.stderr || '');
  process.stderr.write('Unable to parse npm audit output.\n');
  process.exit(1);
}

const lock = JSON.parse(fs.readFileSync(new URL('../package-lock.json', import.meta.url), 'utf8'));
const vulnerabilities = report.vulnerabilities ?? {};
const severityRank = { low: 1, moderate: 2, high: 3, critical: 4 };
const allowedBracesAdvisory = 'https://github.com/advisories/GHSA-vfj7-8cjw-p6xm';
const allowedBuildOnlyChain = new Set(['braces', 'micromatch', '@parcel/watcher']);
const blocked = [];
const waived = [];

for (const [name, vulnerability] of Object.entries(vulnerabilities)) {
  if ((severityRank[vulnerability.severity] ?? 0) < severityRank.high) continue;

  const advisoryUrls = (vulnerability.via ?? [])
    .filter((item) => item && typeof item === 'object')
    .map((item) => item.url)
    .filter(Boolean);
  const lockEntry = lock.packages?.[`node_modules/${name}`];
  const viaPackages = (vulnerability.via ?? [])
    .filter((item) => typeof item === 'string');
  const exactUnfixedBuildOnlyChain =
    allowedBuildOnlyChain.has(name) &&
    vulnerability.severity === 'high' &&
    vulnerability.fixAvailable === false &&
    lockEntry?.dev === true &&
    advisoryUrls.every((url) => url === allowedBracesAdvisory) &&
    viaPackages.every((dependency) => allowedBuildOnlyChain.has(dependency)) &&
    (advisoryUrls.length > 0 || viaPackages.length > 0);

  if (exactUnfixedBuildOnlyChain) {
    waived.push({ name, advisoryUrls, viaPackages });
  } else {
    blocked.push({ name, severity: vulnerability.severity, fixAvailable: vulnerability.fixAvailable, advisoryUrls });
  }
}

if (waived.length) {
  console.warn('Temporary audit exception: the braces advisory is confined to the build-only @parcel/watcher dependency chain and has no upstream fix available.');
  console.warn(`Allowed advisory: ${allowedBracesAdvisory}`);
  console.warn('Remove this exception immediately when a patched dependency path is released.');
}

if (blocked.length) {
  console.error('Blocking high/critical npm audit findings remain:');
  console.error(JSON.stringify(blocked, null, 2));
  process.exit(1);
}

if (result.status === 0 || waived.length > 0) {
  console.log('API asset dependency audit passed with no unwaived high/critical findings.');
  process.exit(0);
}

process.stderr.write(result.stderr || '');
process.exit(result.status ?? 1);
