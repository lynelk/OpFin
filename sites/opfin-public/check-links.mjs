import fs from 'node:fs';

const file = new URL('./index.html', import.meta.url);
const html = fs.readFileSync(file, 'utf8');

const ids = new Set([...html.matchAll(/\sid="([^"]+)"/g)].map((match) => match[1]));
const anchors = [...html.matchAll(/<a\b[^>]*\shref="([^"]+)"[^>]*>([\s\S]*?)<\/a>/gi)]
  .map((match) => ({
    href: match[1],
    text: match[2].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim(),
  }));

const allowedExternalHosts = new Set([
  'opfin-web-production.up.railway.app',
  'opfin-production.up.railway.app'
]);
const restrictedPaths = [
  /^\/admin(?:\/|$)/i,
  /^\/support(?:\/|$)/i,
  /^\/operations?(?:\/|$)/i,
  /^\/dashboard(?:\/|$)/i,
];
const restrictedLabels = [
  /\bborrower\s+dashboard\b/i,
  /\badmin(?:istration)?\s+(?:portal|dashboard|workspace)\b/i,
  /\bsupport\s+(?:portal|dashboard|workspace)\b/i,
  /\boperations?\s+(?:portal|dashboard|workspace)\b/i,
];

const problems = [];

for (const anchor of anchors) {
  const { href, text } = anchor;
  if (!href || href === '#') {
    problems.push('Placeholder or empty anchor href found.');
    continue;
  }

  if (restrictedLabels.some((pattern) => pattern.test(text))) {
    problems.push(`Restricted public navigation label found: "${text}"`);
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
    if (restrictedPaths.some((pattern) => pattern.test(url.pathname)) || /(?:^|[?&])context=operations(?:&|$)/i.test(url.search)) {
      problems.push(`Restricted portal destination exposed publicly: ${href}`);
    }
    continue;
  }

  if (restrictedPaths.some((pattern) => pattern.test(href))) {
    problems.push(`Restricted relative portal destination exposed publicly: ${href}`);
  } else {
    problems.push(`Unexpected relative anchor link in portable public site: ${href}`);
  }
}

if (problems.length) {
  console.error('Public-site link validation failed:');
  problems.forEach((problem) => console.error(`- ${problem}`));
  process.exit(1);
}

console.log(`Validated ${anchors.length} anchor links and ${ids.size} same-page targets.`);
console.log('No placeholder links, missing targets, restricted labels or restricted portal destinations found.');
