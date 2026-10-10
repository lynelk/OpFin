// Generates the OpFin brand toolkit export set (issue #103) from the path-only v3 vector masters.
//
// Platform assets (app icons, Web/mobile symbols, fonts) stay with scripts/prepare-brand-assets.mjs;
// this script never rewrites them. The wordmark and lock-ups are path-only masters derived from the
// licensed Inter SemiBold, so their geometry is deterministic and does not depend on installed fonts.
// They remain source masters here; distribution of additional wordmark/lock-up raster variants is
// separately governed by visual acceptance rather than by a host font dependency.
//
// Run from the repository root after `npm ci` in apps/web (which provides sharp):
//   node scripts/prepare-brand-toolkit.mjs
import { createHash } from 'node:crypto';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(resolve(root, 'apps/web/package.json'));
const sharp = require('sharp');
const tokens = JSON.parse(await readFile(resolve(root, 'brand/opfin.tokens.json'), 'utf8'));
const outDir = 'brand/v3/exports';
const transparent = { r: 0, g: 0, b: 0, alpha: 0 };

const digest = (data) => createHash('sha256').update(data).digest('hex');
// Git hashes the LF form of text files; normalise so the check also works on autocrlf checkouts.
const gitBlob = (data) => {
  const text = Buffer.from(data.toString('utf8').replace(/\r\n/g, '\n'));
  return createHash('sha1').update(`blob ${text.length}\0`).update(text).digest('hex');
};

const manifest = {
  brandSystemVersion: tokens.version,
  generator: { script: 'scripts/prepare-brand-toolkit.mjs', sharp: sharp.versions.sharp, libvips: sharp.versions.vips },
  status: 'Generated from the vector masters; pending visual review (v3 release gate 4). Not a frozen v3.0 release.',
  pending: [
    'Representative mobile, desktop, print and reverse-background visual sign-off.',
  ],
  masters: {},
  files: {},
};

async function master(path, expectedBlob) {
  const data = await readFile(resolve(root, path));
  const blob = gitBlob(data);
  if (expectedBlob && blob !== expectedBlob) {
    throw new Error(`Vector master changed: review and approve ${path} before regenerating exports`);
  }
  manifest.masters[path] = blob;
  return data;
}

async function save(path, data, meta) {
  const target = resolve(root, outDir, path);
  await mkdir(dirname(target), { recursive: true });
  await writeFile(target, data);
  manifest.files[`${outDir}/${path}`] = { sha256: digest(data), ...meta };
}

// LF output, so the committed file and its manifest hash agree on every checkout.
const recolour = (svg, colour) => Buffer.from(svg.toString('utf8').replace(/\r\n/g, '\n').replaceAll('currentColor', colour));

// Renders at (at least) the target resolution so edges stay sharp, then fits exactly.
function raster(svg, viewBoxWidth, width, height = width) {
  const density = Math.max(72, Math.ceil((72 * Math.max(width, height)) / viewBoxWidth));
  return sharp(svg, { density }).resize(width, height, { fit: 'contain', background: transparent }).png({ compressionLevel: 9 }).toBuffer();
}

// ICO container holding PNG images (supported by every current browser and Windows since Vista).
function ico(images) {
  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0);
  header.writeUInt16LE(1, 2);
  header.writeUInt16LE(images.length, 4);
  let offset = 6 + 16 * images.length;
  const entries = images.map(({ size, data }) => {
    const entry = Buffer.alloc(16);
    entry.writeUInt8(size >= 256 ? 0 : size, 0);
    entry.writeUInt8(size >= 256 ? 0 : size, 1);
    entry.writeUInt16LE(1, 4);
    entry.writeUInt16LE(32, 6);
    entry.writeUInt32LE(data.length, 8);
    entry.writeUInt32LE(offset, 12);
    offset += data.length;
    return entry;
  });
  return Buffer.concat([header, ...entries, ...images.map((image) => image.data)]);
}

await rm(resolve(root, outDir), { recursive: true, force: true });

// Monogram: primary, reverse (for Indigo or dark backgrounds) and two monochrome variants.
const symbol = await master(tokens.logo.source, tokens.logo.sourceGitBlob);
const variants = {
  primary: tokens.colours.indigo,
  reverse: tokens.colours.ivory,
  'mono-dark': tokens.colours.ink,
  'mono-light': tokens.colours.white,
};
for (const [variant, colour] of Object.entries(variants)) {
  const svg = recolour(symbol, colour);
  await save(`symbol/opfin-symbol-${variant}.svg`, svg, { source: tokens.logo.source, variant, colour, format: 'svg' });
  for (const size of [64, 128, 256, 512, 1024, 2048]) {
    await save(`symbol/png/opfin-symbol-${variant}-${size}.png`, await raster(svg, 512, size),
      { source: tokens.logo.source, variant, colour, format: 'png', width: size, height: size, background: 'transparent' });
  }
}

// App icon, browser icons and profile images from the app-icon master (no wordmark inside the icon).
const icon = await master(tokens.appIcon.source, tokens.appIcon.sourceGitBlob);
for (const size of [1024, 512, 192, 180, 152, 120]) {
  await save(`app-icon/opfin-app-icon-${size}.png`, await raster(icon, 1024, size),
    { source: tokens.appIcon.source, format: 'png', width: size, height: size, use: size === 180 ? 'apple-touch-icon' : 'app or store icon' });
}
const favicons = [];
for (const size of [16, 32, 48]) {
  const data = await raster(icon, 1024, size);
  favicons.push({ size, data });
  await save(`favicon/favicon-${size}.png`, data, { source: tokens.appIcon.source, format: 'png', width: size, height: size, use: 'favicon' });
}
await save('favicon/favicon.ico', ico(favicons), { source: tokens.appIcon.source, format: 'ico', sizes: [16, 32, 48], use: 'favicon' });
for (const size of [400, 800]) {
  await save(`profile/opfin-profile-${size}.png`, await raster(icon, 1024, size),
    { source: tokens.appIcon.source, format: 'png', width: size, height: size, use: 'social or messaging profile image; check the circular crop' });
}

// Progress Path motif, transparent.
const motifPath = 'brand/v3/assets/opfin-progress-path.svg';
const motif = await master(motifPath);
for (const width of [720, 1440]) {
  const height = Math.round((width * 420) / 720);
  await save(`motif/opfin-progress-path-${width}.png`, await raster(motif, 720, width, height),
    { source: motifPath, format: 'png', width, height, background: 'transparent' });
}

await writeFile(resolve(root, outDir, 'EXPORT_MANIFEST.json'), JSON.stringify(manifest, null, 2) + '\n');
console.log(JSON.stringify({ brandSystemVersion: tokens.version, exports: Object.keys(manifest.files).length, masters: Object.keys(manifest.masters) }));
