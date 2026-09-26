'use server';

import { getAccessToken } from '@/lib/auth/session';
import { positiveId, type ClubAction, type ClubResult, type RecordValue } from '@/lib/club-accounting/contracts';

async function apiRequest(path: string, method = 'GET', input: RecordValue = {}): Promise<ClubResult> {
  const token = await getAccessToken();
  if (!token) return { ok: false, status: 401, message: 'Sign in again to use club accounting.' };
  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL?.replace(/\/$/, '');
  if (!base) return { ok: false, status: 503, message: 'The accounting API is not configured.' };
  if (JSON.stringify(input).length > 200000) return { ok: false, status: 422, message: 'Split this request into smaller reviewed records.' };
  try {
    const response = await fetch(base + path, {
      method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
      body: method === 'POST' ? JSON.stringify(input) : undefined,
      cache: 'no-store', redirect: 'error', signal: AbortSignal.timeout(30000),
    });
    const envelope = await response.json() as { success?: boolean; data?: RecordValue; message?: unknown };
    if (!response.ok || envelope.success !== true) {
      return { ok: false, status: response.status, message: response.status >= 500
        ? 'The service is unavailable. Open Saved requests before creating another instruction.'
        : typeof envelope.message === 'string' ? envelope.message : 'The accounting request was not accepted.' };
    }
    if (!envelope.data || typeof envelope.data !== 'object' || Array.isArray(envelope.data)) throw new Error('Invalid response');
    return { ok: true, data: envelope.data };
  } catch {
    return { ok: false, message: 'The result is uncertain. Open Saved requests to resume the original request; do not create a duplicate.' };
  }
}

export async function clubAction(action: ClubAction, spaceId: number, bookId: number | null = null,
  input: RecordValue = {}, targetId: number | null = null): Promise<ClubResult> {
  try {
    positiveId(spaceId);
    const root = '/financial-spaces/' + spaceId + '/accounting/books';
    const book = bookId === null ? '' : root + '/' + positiveId(bookId);
    // Prepare is committed before the original domain submission. Recovery is
    // server-side, encrypted and shared between Web and App, not localStorage.
    if (action === 'submit' || action === 'issue-statement') {
      if (!book) throw new Error('Book required');
      const purpose = action === 'submit' ? 'instruction' : 'statement';
      const prepared = await apiRequest(book + '/client-requests/prepare/' + purpose, 'POST', input);
      if (!prepared.ok) return prepared;
      const saved = prepared.data.client_request as RecordValue;
      const submitted = await apiRequest(book + '/client-requests/submit', 'POST', {
        reference: saved.reference, content_hash: saved.content_hash,
      });
      // Once saved, editing the original input is not safe. Recovery offers
      // explicit cancellation of a genuinely unsubmitted request instead.
      return submitted.ok ? submitted : { ...submitted, status: submitted.status === 422 ? 409 : submitted.status };
    }
    let path: string;
    let method = 'GET';
    switch (action) {
      case 'schema': path = '/accounting/club-schema'; break;
      case 'profile': path = '/profile'; break;
      case 'books': path = root; break;
      case 'create-book': path = root; method = 'POST'; break;
      case 'catalogue': case 'instructions': case 'report': case 'journals': case 'integrity': case 'statements':
        if (!book) throw new Error('Book required'); path = book + '/' + action; break;
      case 'instruction': case 'preview': case 'approve': case 'reject': case 'cancel':
        if (!book) throw new Error('Book required');
        path = book + '/instructions/' + positiveId(targetId) + (action === 'instruction' ? '' : '/' + action);
        if (action !== 'instruction') method = 'POST'; break;
      case 'statement': if (!book) throw new Error('Book required'); path = book + '/statements/' + positiveId(targetId); break;
      default: return { ok: false, status: 422, message: 'Unsupported accounting action.' };
    }
    if (method === 'GET') {
      const allowed = new Set(['page', 'limit', 'status', 'period_start', 'period_end', 'member_user_id']);
      const query = new URLSearchParams();
      for (const [key, value] of Object.entries(input)) {
        if (!allowed.has(key) || !['string', 'number'].includes(typeof value)) throw new Error('Invalid query');
        query.set(key, String(value));
      }
      if (query.size) path += '?' + query;
    }
    return apiRequest(path, method, input);
  } catch { return { ok: false, status: 422, message: 'Choose a valid accounting record and supported operation.' }; }
}

export async function clubRecoveryAction(action: 'list' | 'inspect' | 'resume' | 'acknowledge' | 'cancel',
  spaceId?: number, bookId?: number, identity: RecordValue = {}, page = 1): Promise<ClubResult> {
  try {
    if (action === 'list') {
      positiveId(page);
      return apiRequest('/accounting/saved-requests?page=' + page + (spaceId !== undefined ? '&space_id=' + positiveId(spaceId) : ''));
    }
    if (!['inspect', 'resume', 'acknowledge', 'cancel'].includes(action)) throw new Error('Invalid operation');
    const root = '/financial-spaces/' + positiveId(spaceId) + '/accounting/books/' + positiveId(bookId) + '/client-requests/';
    if (typeof identity.reference !== 'string' || typeof identity.content_hash !== 'string') throw new Error('Invalid identity');
    return apiRequest(root + (action === 'resume' ? 'submit' : action), 'POST', {
      reference: identity.reference, content_hash: identity.content_hash,
    });
  } catch { return { ok: false, status: 422, message: 'Choose a saved request belonging to the selected book.' }; }
}

export async function clubHistoryAction(): Promise<ClubResult> {
  return apiRequest('/accounting/my-club-books');
}
