"server-only";

import { OpfinApiError, classifyStatus } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;

async function jsonRequest<T>(
  path: string,
  token: string | undefined,
  init: RequestInit & { bodyJson?: unknown } = {}
): Promise<T> {
  if (!API_BASE_URL) {
    throw new OpfinApiError("server", "OpFin API base URL is not configured.");
  }

  const response = await fetch(API_BASE_URL + path, {
    ...init,
    body: init.bodyJson ? JSON.stringify(init.bodyJson) : init.body,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: "Bearer " + token } : {}),
      ...(init.headers ?? {})
    },
    cache: "no-store"
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new OpfinApiError(
      classifyStatus(response.status),
      typeof payload.message === "string" ? payload.message : "Treasury request failed.",
      response.status,
      typeof payload.errors === "object" && payload.errors ? payload.errors : {}
    );
  }

  return payload.data as T;
}

export type TreasuryAccount = {
  id: number;
  public_id: string;
  account_name: string;
  account_type: string;
  institution_name?: string | null;
  account_reference_masked?: string | null;
  currency: string;
  opening_balance_minor: number;
  current_balance_minor: number;
  balance_as_of?: string | null;
  current_balance_as_of?: string | null;
  status: string;
};

export type TreasuryTransaction = {
  id: number;
  public_id: string;
  transaction_reference?: string | null;
  transaction_type: string;
  direction: "debit" | "credit";
  amount_minor: number;
  currency: string;
  description: string;
  counterparty_name?: string | null;
  transaction_date: string;
  value_date?: string | null;
  reconciliation_status: string;
};

export type ReconciliationTodo = {
  type: "statement_row" | "book_transaction" | "balance_variance";
  row_id?: number;
  transaction_id?: number;
  exception_type?: string | null;
  date?: string | null;
  description?: string | null;
  amount_minor?: number;
  opening_balance_variance_minor?: number;
  closing_balance_variance_minor?: number;
  direction?: string;
  suggested_matches?: Array<{
    transaction_id: number;
    reference?: string | null;
    date?: string | null;
    description: string;
    amount_minor: number;
    direction: string;
    confidence_percent: number;
    match_method: string;
  }>;
  allowed_actions: string[];
};

export type StatementImport = {
  id: number;
  public_id: string;
  original_filename?: string | null;
  period_start?: string | null;
  period_end?: string | null;
  row_count: number;
  matched_count: number;
  exception_count: number;
  status: string;
  confirmation_status: string;
  confirmed_at?: string | null;
  review_todos?: ReconciliationTodo[] | null;
  summary?: Record<string, unknown> | null;
};

export type GeneratedStatement = {
  id: number;
  public_id: string;
  statement_number: string;
  treasury_account_id?: number | null;
  statement_scope: "account" | "consolidated";
  period_start: string;
  period_end: string;
  opening_balance_minor: number;
  closing_balance_minor: number;
  total_debits_minor: number;
  total_credits_minor: number;
  transaction_count: number;
  reconciliation_status: string;
  content_hash: string;
  generated_at: string;
};

export const financialSpaceStatementsApi = {
  accounts: (spaceId: number, token?: string) =>
    jsonRequest<{ accounts: TreasuryAccount[] }>(
      "/financial-spaces/" + spaceId + "/treasury/accounts",
      token
    ),

  createAccount: (
    spaceId: number,
    payload: Record<string, unknown>,
    token?: string
  ) =>
    jsonRequest<{ account: TreasuryAccount }>(
      "/financial-spaces/" + spaceId + "/treasury/accounts",
      token,
      { method: "POST", bodyJson: payload }
    ),

  transactions: (spaceId: number, accountId: number, token?: string) =>
    jsonRequest<{ transactions: TreasuryTransaction[] }>(
      "/financial-spaces/" + spaceId + "/treasury/accounts/" + accountId + "/transactions",
      token
    ),

  recordTransaction: (
    spaceId: number,
    accountId: number,
    payload: Record<string, unknown>,
    token?: string
  ) =>
    jsonRequest<{ transaction: TreasuryTransaction }>(
      "/financial-spaces/" + spaceId + "/treasury/accounts/" + accountId + "/transactions",
      token,
      { method: "POST", bodyJson: payload }
    ),

  imports: (spaceId: number, accountId: number, token?: string) =>
    jsonRequest<{ imports: StatementImport[] }>(
      "/financial-spaces/" + spaceId + "/treasury/accounts/" + accountId + "/statement-imports",
      token
    ),

  importCsv: async (
    spaceId: number,
    accountId: number,
    formData: FormData,
    token?: string
  ): Promise<{ import: StatementImport }> => {
    if (!API_BASE_URL) {
      throw new OpfinApiError("server", "OpFin API base URL is not configured.");
    }

    const response = await fetch(
      API_BASE_URL +
        "/financial-spaces/" +
        spaceId +
        "/treasury/accounts/" +
        accountId +
        "/statement-imports",
      {
        method: "POST",
        headers: {
          Accept: "application/json",
          ...(token ? { Authorization: "Bearer " + token } : {})
        },
        body: formData,
        cache: "no-store"
      }
    );

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new OpfinApiError(
        classifyStatus(response.status),
        typeof payload.message === "string" ? payload.message : "Statement import failed.",
        response.status,
        typeof payload.errors === "object" && payload.errors ? payload.errors : {}
      );
    }

    return payload.data as { import: StatementImport };
  },

  importDetail: (spaceId: number, importId: number, token?: string) =>
    jsonRequest<{ import: StatementImport & { rows?: unknown[] } }>(
      "/financial-spaces/" + spaceId + "/statement-imports/" + importId,
      token
    ),

  reconcile: (spaceId: number, importId: number, token?: string) =>
    jsonRequest<{ import: StatementImport }>(
      "/financial-spaces/" + spaceId + "/statement-imports/" + importId + "/reconcile",
      token,
      { method: "POST" }
    ),

  resolveRow: (
    spaceId: number,
    rowId: number,
    payload: {
      action: "match_transaction" | "create_book_entry" | "mark_external_only" | "mark_duplicate";
      transaction_id?: number;
      reason?: string;
    },
    token?: string
  ) =>
    jsonRequest<{ row: Record<string, unknown> }>(
      "/financial-spaces/" + spaceId + "/statement-rows/" + rowId + "/resolve",
      token,
      { method: "POST", bodyJson: payload }
    ),

  acceptBookOnly: (
    spaceId: number,
    importId: number,
    transactionId: number,
    reason: string,
    token?: string
  ) =>
    jsonRequest<{ transaction: TreasuryTransaction }>(
      "/financial-spaces/" +
        spaceId +
        "/statement-imports/" +
        importId +
        "/book-transactions/" +
        transactionId +
        "/accept",
      token,
      { method: "POST", bodyJson: { reason } }
    ),

  acceptBalanceVariance: (
    spaceId: number,
    importId: number,
    reason: string,
    token?: string
  ) =>
    jsonRequest<{ import: StatementImport }>(
      "/financial-spaces/" +
        spaceId +
        "/statement-imports/" +
        importId +
        "/balance-variance",
      token,
      { method: "POST", bodyJson: { reason } }
    ),

  confirmReconciliation: (
    spaceId: number,
    importId: number,
    note?: string,
    token?: string
  ) =>
    jsonRequest<{ import: StatementImport }>(
      "/financial-spaces/" + spaceId + "/statement-imports/" + importId + "/confirm",
      token,
      { method: "POST", bodyJson: { note } }
    ),

  matchRow: (
    spaceId: number,
    rowId: number,
    transactionId: number,
    token?: string
  ) =>
    jsonRequest<{ row: Record<string, unknown> }>(
      "/financial-spaces/" + spaceId + "/statement-rows/" + rowId + "/match",
      token,
      { method: "POST", bodyJson: { transaction_id: transactionId } }
    ),

  statements: (spaceId: number, accountId?: number, token?: string) =>
    jsonRequest<{ statements: GeneratedStatement[] }>(
      "/financial-spaces/" +
        spaceId +
        "/statements" +
        (accountId ? "?account_id=" + accountId : ""),
      token
    ),

  generateConsolidated: (
    spaceId: number,
    from: string,
    to: string,
    token?: string
  ) =>
    jsonRequest<{
      statement: GeneratedStatement;
      sections: Array<Record<string, unknown>>;
      totals_by_currency: Record<string, Record<string, number>>;
      position_by_currency: Record<string, Record<string, number>>;
      downloads: { html: string; csv: string };
    }>(
      "/financial-spaces/" + spaceId + "/statements/consolidated",
      token,
      {
        method: "POST",
        bodyJson: { from, to }
      }
    ),

  generate: (
    spaceId: number,
    accountId: number,
    from: string,
    to: string,
    token?: string
  ) =>
    jsonRequest<{
      statement: GeneratedStatement;
      rows: Array<Record<string, unknown>>;
      downloads: { html: string; csv: string };
    }>(
      "/financial-spaces/" +
        spaceId +
        "/treasury/accounts/" +
        accountId +
        "/statements",
      token,
      {
        method: "POST",
        bodyJson: { from, to }
      }
    ),

  statement: (spaceId: number, statementId: number, token?: string) =>
    jsonRequest<{
      statement: GeneratedStatement;
      account: TreasuryAccount | null;
      rows: Array<Record<string, unknown>>;
      sections: Array<Record<string, unknown>>;
      totals_by_currency: Record<string, Record<string, number>>;
      position_by_currency: Record<string, Record<string, number>>;
    }>(
      "/financial-spaces/" + spaceId + "/statements/" + statementId,
      token
    )
};

export function apiDownloadUrl(path: string): string | null {
  if (!API_BASE_URL) return null;
  return API_BASE_URL + path;
}
