"use server";

import { redirect } from "next/navigation";
import { financialSpaceStatementsApi } from "@/lib/api/financial-space-statements";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function integer(formData: FormData, key: string): number {
  const parsed = Number(value(formData, key));
  return Number.isFinite(parsed) ? Math.trunc(parsed) : 0;
}

function destination(spaceId: number, params: Record<string, string>): never {
  redirect("/spaces/" + spaceId + "/treasury?" + new URLSearchParams(params).toString());
}

function fail(spaceId: number, error: unknown, fallback: string): never {
  destination(spaceId, {
    error: error instanceof OpfinApiError ? error.kind : "server",
    message: error instanceof Error ? error.message : fallback
  });
}

export async function createTreasuryAccountAction(formData: FormData) {
  const token = await getAccessToken();
  const spaceId = integer(formData, "space_id");
  try {
    await financialSpaceStatementsApi.createAccount(
      spaceId,
      {
        account_name: value(formData, "account_name"),
        account_type: value(formData, "account_type"),
        institution_name: value(formData, "institution_name") || undefined,
        account_reference: value(formData, "account_reference") || undefined,
        currency: value(formData, "currency") || "UGX",
        opening_balance_minor: integer(formData, "opening_balance_minor"),
        balance_as_of: value(formData, "balance_as_of") || undefined
      },
      token
    );
  } catch (error) {
    fail(spaceId, error, "Unable to create treasury account.");
  }
  destination(spaceId, { status: "account-created" });
}

export async function recordTreasuryTransactionAction(formData: FormData) {
  const token = await getAccessToken();
  const spaceId = integer(formData, "space_id");
  const accountId = integer(formData, "account_id");
  try {
    await financialSpaceStatementsApi.recordTransaction(
      spaceId,
      accountId,
      {
        transaction_reference: value(formData, "transaction_reference") || undefined,
        transaction_type: value(formData, "transaction_type") || "other",
        direction: value(formData, "direction"),
        amount_minor: integer(formData, "amount_minor"),
        description: value(formData, "description"),
        counterparty_name: value(formData, "counterparty_name") || undefined,
        transaction_date: value(formData, "transaction_date"),
        value_date: value(formData, "value_date") || undefined
      },
      token
    );
  } catch (error) {
    fail(spaceId, error, "Unable to record treasury transaction.");
  }
  destination(spaceId, { account: String(accountId), status: "transaction-recorded" });
}

export async function importTreasuryStatementAction(formData: FormData) {
  const token = await getAccessToken();
  const spaceId = integer(formData, "space_id");
  const accountId = integer(formData, "account_id");
  const file = formData.get("statement_file");
  if (!(file instanceof File) || file.size === 0) {
    destination(spaceId, { account: String(accountId), error: "validation", message: "Choose a CSV statement file." });
  }

  const upload = new FormData();
  upload.set("statement_file", file);
  upload.set("minor_unit_exponent", value(formData, "minor_unit_exponent") || "0");
  upload.set(
    "mapping",
    JSON.stringify({
      date: value(formData, "date_column") || "Date",
      value_date: value(formData, "value_date_column") || undefined,
      description: value(formData, "description_column") || "Description",
      reference: value(formData, "reference_column") || undefined,
      debit: value(formData, "debit_column") || undefined,
      credit: value(formData, "credit_column") || undefined,
      amount: value(formData, "amount_column") || undefined,
      direction: value(formData, "direction_column") || undefined,
      balance: value(formData, "balance_column") || undefined
    })
  );
  if (value(formData, "opening_balance_minor")) {
    upload.set("opening_balance_minor", value(formData, "opening_balance_minor"));
  }
  if (value(formData, "closing_balance_minor")) {
    upload.set("closing_balance_minor", value(formData, "closing_balance_minor"));
  }

  try {
    const result = await financialSpaceStatementsApi.importCsv(spaceId, accountId, upload, token);
    destination(spaceId, {
      account: String(accountId),
      import: String(result.import.id),
      status: "statement-imported"
    });
  } catch (error) {
    fail(spaceId, error, "Unable to import statement.");
  }
}

export async function reconcileTreasuryStatementAction(formData: FormData) {
  const token = await getAccessToken();
  const spaceId = integer(formData, "space_id");
  const accountId = integer(formData, "account_id");
  const importId = integer(formData, "import_id");
  try {
    await financialSpaceStatementsApi.reconcile(spaceId, importId, token);
  } catch (error) {
    fail(spaceId, error, "Unable to reconcile statement.");
  }
  destination(spaceId, {
    account: String(accountId),
    import: String(importId),
    status: "reconciled"
  });
}


export async function generateTreasuryStatementAction(formData: FormData) {
  const token = await getAccessToken();
  const spaceId = integer(formData, "space_id");
  const accountId = integer(formData, "account_id");
  const from = value(formData, "from");
  const to = value(formData, "to");
  try {
    const result = await financialSpaceStatementsApi.generate(
      spaceId,
      accountId,
      from,
      to,
      token
    );
    destination(spaceId, {
      account: String(accountId),
      statement: String(result.statement.id),
      status: "statement-generated"
    });
  } catch (error) {
    fail(spaceId, error, "Unable to generate statement.");
  }
}


export async function matchTreasuryStatementRowAction(formData: FormData) {
  const token = await getAccessToken();
  const spaceId = integer(formData, "space_id");
  const accountId = integer(formData, "account_id");
  const importId = integer(formData, "import_id");
  const rowId = integer(formData, "row_id");
  const transactionId = integer(formData, "transaction_id");

  try {
    await financialSpaceStatementsApi.matchRow(
      spaceId,
      rowId,
      transactionId,
      token
    );
  } catch (error) {
    fail(spaceId, error, "Unable to match this statement row.");
  }

  destination(spaceId, {
    account: String(accountId),
    import: String(importId),
    status: "row-matched"
  });
}
