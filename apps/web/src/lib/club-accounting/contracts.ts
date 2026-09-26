export type Json = null | boolean | number | string | Json[] | { [key: string]: Json };
export type RecordValue = { [key: string]: Json };
export type Field = {
  key: string; label: string; type: 'integer' | 'string' | 'date' | 'month' | 'array';
  required: boolean; source?: string; enum?: string[]; items?: Field[]; items_type?: string;
  minimum?: number; maximum?: number; maxLength?: number;
};
export type Operation = { type: string; title: string; effect: string; fields: Field[] };
export type Instruction = {
  id: number; reference: string; type: string; business_date: string; status: string;
  maker_id: number; checker_id?: number; payload_hash: string; payload: RecordValue; result?: RecordValue;
};
export type Book = {
  id: number; public_id: string; currency: string; ownership_model: string; status: string;
  cutover_date: string; closed_through?: string; my_position?: RecordValue;
};
export type ClubAction = 'schema' | 'books' | 'create-book' | 'catalogue' | 'instructions' |
  'submit' | 'instruction' | 'preview' | 'approve' | 'reject' | 'cancel' |
  'report' | 'journals' | 'integrity' | 'statements' | 'issue-statement' | 'statement' | 'profile';
export type ClubResult = { ok: true; data: RecordValue } | { ok: false; message: string; status?: number };

export function positiveId(value: unknown): number {
  if (typeof value !== 'number' || !Number.isSafeInteger(value) || value <= 0) throw new Error('Choose a valid record.');
  return value;
}
export function integerInput(value: unknown, minimum = 0): number {
  const text = String(value ?? '').trim();
  if (!/^[0-9]+$/.test(text)) throw new Error('Enter a whole number, without commas or decimals.');
  const number = Number(text);
  if (!Number.isSafeInteger(number) || number < minimum) throw new Error('The number is outside the supported range.');
  return number;
}
export function isoDate(value: string): string {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) throw new Error('Choose a calendar date.');
  const date = new Date(value + 'T00:00:00.000Z');
  if (!Number.isFinite(date.getTime()) || date.toISOString().slice(0,10) !== value) throw new Error('Choose a real calendar date.');
  return value;
}
export function validateFields(fields: Field[], raw: RecordValue): RecordValue {
  const result: RecordValue = {};
  for (const field of fields) {
    const value = raw[field.key];
    if (value === undefined || value === null || value === '') {
      if (field.required) throw new Error(field.label + ' is required.');
      continue;
    }
    if (field.type === 'array') {
      if (!Array.isArray(value) || value.length > 1000) throw new Error(field.label + ' has too many or invalid rows.');
      result[field.key] = value.map(row => {
        if (field.items_type === 'integer') return integerInput(row,1);
        if (row === null || Array.isArray(row) || typeof row !== 'object') throw new Error('Invalid ' + field.label + ' row.');
        return validateFields(field.items ?? [], row);
      });
    } else if (field.type === 'integer') {
      const number = integerInput(value,field.minimum ?? 0);
      if (field.maximum !== undefined && number > field.maximum) throw new Error(field.label + ' is too large.');
      result[field.key] = number;
    } else {
      if (typeof value !== 'string') throw new Error(field.label + ' must be text.');
      const text = value.trim();
      if (text.length > (field.maxLength ?? 1000)) throw new Error(field.label + ' is too long.');
      if (field.enum && !field.enum.includes(text)) throw new Error('Choose a supported ' + field.label + '.');
      if (field.type === 'date') isoDate(text);
      if (field.type === 'month') isoDate(text + '-01');
      result[field.key] = text;
    }
  }
  for (const key of Object.keys(raw)) if (!fields.some(field => field.key === key)) throw new Error('Unsupported field: ' + key);
  return result;
}
export function sameIntent(left: RecordValue, right: RecordValue): boolean {
  const canonical = (value: Json): string => {
    if (Array.isArray(value)) return '[' + value.map(canonical).join(',') + ']';
    if (value !== null && typeof value === 'object') return '{' + Object.keys(value).sort().map(key => JSON.stringify(key)+':'+canonical(value[key])).join(',') + '}';
    return JSON.stringify(value);
  };
  return canonical(left) === canonical(right);
}
export function humanLabel(text: string): string {
  return text.replace(/_/g,' ').replace(/\bminor\b/g,'(minor units)').replace(/\bmicro\b/g,'(micro-units)');
}
