import { isIP } from 'node:net';
import { pathToFileURL, URL } from 'node:url';
import console from 'node:console';

export function validateProductionApiUrl(value) {
  if (typeof value !== 'string' || !value || value.trim() !== value) {
    throw new Error('NEXT_PUBLIC_OPFIN_API_URL is required.');
  }
  let url;
  try { url = new URL(value); } catch { throw new Error('Invalid API URL.'); }
  const host = url.hostname.toLowerCase().replace(/\.$/, '');
  const labels = host.split('.');
  const local = ['localhost', 'local', 'internal', 'test', 'invalid'].some(
    (suffix) => host === suffix || host.endsWith(`.${suffix}`),
  );
  if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash
      || !['/api', '/api/'].includes(url.pathname) || local || isIP(host)
      || labels.length < 2 || !/[a-z]/i.test(labels.at(-1))
      || labels.some((label) => !/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(label))) {
    throw new Error('API URL must use HTTPS, a public DNS hostname and /api, without credentials, query or fragment.');
  }
  return url;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    validateProductionApiUrl(process.env.NEXT_PUBLIC_OPFIN_API_URL);
    console.log('Production API URL configuration verified.');
  } catch {
    console.error('Invalid NEXT_PUBLIC_OPFIN_API_URL: require HTTPS with a public DNS hostname and /api.');
    process.exitCode = 1;
  }
}
