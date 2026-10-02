import fs from 'node:fs';

const file = new URL('./index.html', import.meta.url);
const html = fs.readFileSync(file, 'utf8');

const ids = new Set([...html.matchAll(/\sid="([^"]+)"/g)].map((match) => match[1]));
const hrefs = [...html.matchAll(/<a\b[^>]*\shref="([^"]+)"/g)].map((match) => match[1]);

const allowedExternalHosts = new Set([
  'opfin-web-production.up.railway.app',
  'opfin-production.up.railway.app'
]);

const problems = [];

for (const href of hrefs) {
  if (!href || href === '#') {
    problems.push('Placeholder or empty anchor href found.');
    continue;
  }

  if (href.startsWith('#')) {
    const target = href.slice(1);
    if (!ids.has(target)) problems.push(`Missing same-page target: ${href}`);
    continue;
  }

  if (href.startsWith('http://') || href.startsWith('https://')) {
    const url = new URL(href);
    if (url.protocol !== 'https:') problems.push(`External link must use HTTPS: ${href}`);
    if (!allowedExternalHosts.has(url.hostname)) problems.push(`External host is not approved in this candidate: ${url.hostname}`);
    continue;
  }

  problems.push(`Unexpected relative anchor link in portable public site: ${href}`);
}

const publiclyVisibleRestrictedLabels = [
  'Borrower Dashboard',
  'Admin Portal',
  'Support Portal',
  'Operations Portal'
];

for (const label of publiclyVisibleRestrictedLabels) {
  if (html.includes(`>${label}<`)) problems.push(`Restricted public navigation label found: ${label}`);
}

if (problems.length) {
  console.error('Public-site link validation failed:');
  problems.forEach((problem) => console.error(`- ${problem}`));
  process.exit(1);
}

console.log(`Validated ${hrefs.length} anchor links and ${ids.size} same-page targets.`);
console.log('No placeholder links or conspicuous restricted portal labels found.');
