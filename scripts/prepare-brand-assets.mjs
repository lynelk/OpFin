import { createHash } from 'node:crypto';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(resolve(root, 'apps/web/package.json'));
const sharp = require('sharp');
const tokens = JSON.parse(await readFile(resolve(root, 'brand/opfin.tokens.json'), 'utf8'));
const manifest = { source: tokens.logo, fontSource: tokens.fontSource, files: {} };
const digest = (data) => createHash('sha256').update(data).digest('hex');
const gitBlob = (data) => createHash('sha1').update(`blob ${data.length}\0`).update(data).digest('hex');
async function save(path, data) {
  const target = resolve(root, path);
  await mkdir(dirname(target), { recursive: true });
  await writeFile(target, data);
  manifest.files[path] = digest(data);
}
async function officialAsset(path, expectedBlob) {
  const url = `https://raw.githubusercontent.com/rsms/inter/${tokens.fontSource.commit}/${path}`;
  const response = await fetch(url, { signal: AbortSignal.timeout(30000), redirect: 'error' });
  if (!response.ok) throw new Error(`Official font source unavailable: ${response.status}`);
  const data = Buffer.from(await response.arrayBuffer());
  if (gitBlob(data) !== expectedBlob) throw new Error(`Official source integrity mismatch: ${path}`);
  return data;
}
const woff = await officialAsset('docs/font-files/InterVariable.woff2', '5a8d3e72ad7ffb62af3b146e1b1f54ab5813a212');
const ttf = await officialAsset('docs/font-files/InterVariable.ttf', '4ab79e0102bbe0ffa1ed879b13e52ac8c6487833');
const licence = await officialAsset('LICENSE.txt', '9b2ca37b3ffc77391d8b2ebef4a974ef32bf46ea');
await save('apps/web/public/brand/InterVariable.woff2', woff);
await save('apps/web/public/brand/INTER-LICENSE.txt', licence);
await save('apps/client/assets/brand/InterVariable.ttf', ttf);
await save('apps/client/assets/brand/INTER-LICENSE.txt', licence);

const source = await readFile(resolve(root, tokens.logo.source));
if (gitBlob(source) !== tokens.logo.sourceGitBlob) throw new Error('Logo source changed: review the master before regenerating');
const { data, info } = await sharp(source, { limitInputPixels: 60000000 })
  .resize(768, 768, { fit: 'inside' }).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
let hasTransparency = false;
for (let i = 3; i < data.length; i += 4) if (data[i] < 10) { hasTransparency = true; break; }
let left = info.width, top = info.height, right = -1, bottom = -1;
for (let y = 0; y < info.height; y++) for (let x = 0; x < info.width; x++) {
  const p = (y * info.width + x) * 4;
  if (!hasTransparency && Math.min(data[p], data[p + 1], data[p + 2]) > 245) data[p + 3] = 0;
  if (data[p + 3] > 16) { left = Math.min(left, x); top = Math.min(top, y); right = Math.max(right, x); bottom = Math.max(bottom, y); }
}
if (right <= left || bottom <= top) throw new Error('Source logo has no usable visible artwork');
const width = right - left + 1, height = bottom - top + 1;
function recolour(light, dark = light) {
  const out = Buffer.from(data);
  const rgb = (colour) => [1, 3, 5].map((i) => parseInt(colour.slice(i, i + 2), 16));
  const a = rgb(light), b = rgb(dark);
  for (let p = 0; p < out.length; p += 4) {
    const colour = (data[p] + data[p + 1] + data[p + 2]) / 3 > 105 ? a : b;
    out[p] = colour[0]; out[p + 1] = colour[1]; out[p + 2] = colour[2];
  }
  return sharp(out, { raw: { width: info.width, height: info.height, channels: 4 } })
    .extract({ left, top, width, height }).resize(512, 512, { fit: 'inside' }).png().toBuffer();
}
const mono = await recolour(tokens.colours.indigo);
const reverse = await recolour(tokens.colours.ivory);
await save('apps/web/public/brand/opfin-symbol.png', mono);
await save('apps/web/public/brand/opfin-symbol-reverse.png', reverse);
await save('apps/client/assets/brand/opfin-symbol.png', mono);
await save('apps/client/assets/brand/opfin-symbol-reverse.png', reverse);
const iconSymbol = await sharp(await recolour(tokens.colours.apricot, tokens.colours.ivory))
  .resize(720, 720, { fit: 'inside' }).png().toBuffer();
const icon = await sharp({ create: { width: 1024, height: 1024, channels: 4, background: tokens.colours.indigo } })
  .composite([{ input: iconSymbol, gravity: 'centre' }]).png().toBuffer();
await save('apps/client/assets/brand/opfin-app-icon.png', icon);
await save('apps/web/src/app/icon.png', await sharp(icon).resize(192, 192).png().toBuffer());
for (const [density, size] of Object.entries({ mdpi: 48, hdpi: 72, xhdpi: 96, xxhdpi: 144, xxxhdpi: 192 })) {
  await save(`apps/client/android/app/src/main/res/mipmap-${density}/ic_launcher.png`, await sharp(icon).resize(size, size).png().toBuffer());
}
const iosContentsPath = resolve(root, 'apps/client/ios/Runner/Assets.xcassets/AppIcon.appiconset/Contents.json');
try {
  const contents = JSON.parse(await readFile(iosContentsPath, 'utf8'));
  for (const image of contents.images) {
    if (!image.filename || !image.size || !image.scale) continue;
    const size = Math.round(parseFloat(image.size) * parseFloat(image.scale));
    if (!Number.isFinite(size) || size < 16 || size > 1024 || image.filename.includes('/')) throw new Error('Unexpected iOS icon specification');
    await save(`apps/client/ios/Runner/Assets.xcassets/AppIcon.appiconset/${image.filename}`, await sharp(icon).resize(size, size).removeAlpha().png().toBuffer());
  }
} catch (error) {
  if (error.code !== 'ENOENT') throw error;
  console.log('iOS icon catalog is generated by the existing preparation tool; use assets/brand/opfin-app-icon.png.');
}
await writeFile(resolve(root, 'brand/asset-manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
console.log(JSON.stringify({ sourceHasTransparency: hasTransparency, sourceBounds: { left, top, width, height }, generatedAssets: Object.keys(manifest.files).length }));
