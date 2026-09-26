"use server";

import { redirect } from "next/navigation";
import { intelligenceApi, IntelligenceApiError } from "@/lib/api/financial-intelligence";
import { getAccessToken } from "@/lib/auth/session";
import { destination, integerInput, positiveId, type IntelligenceTab } from "@/lib/financial-intelligence/presentation";

function text(form: FormData, key: string, required = true): string {
  const value = form.get(key);
  if (typeof value !== "string" || (required && !value.trim())) throw new Error(`Missing ${key}.`);
  return value.trim();
}
function optional(form: FormData, key: string): string | undefined {
  const value = form.get(key);
  return typeof value === "string" && value.trim() ? value.trim() : undefined;
}
function record(form: FormData, key: string): number { return positiveId(text(form, key)); }
function jsonObject(raw: string | undefined): Record<string, unknown> {
  if (!raw) return {};
  const data: unknown = JSON.parse(raw);
  if (!data || typeof data !== "object" || Array.isArray(data)) throw new Error("Use a JSON object.");
  return data as Record<string, unknown>;
}
function upload(form: FormData, key: string, required: boolean): File | undefined {
  const value = form.get(key);
  if (value instanceof File && value.size > 0 && value.size <= 25000000) return value;
  if (required) throw new Error("Choose a file within the upload limit.");
  if (value instanceof File && value.size > 25000000) throw new Error("File exceeds the upload limit.");
  return undefined;
}
function idempotency(form: FormData): string {
  const key = text(form, "instruction_key");
  if (!/^[A-Za-z0-9:_-]{8,120}$/.test(key)) throw new Error("Invalid instruction key.");
  return key;
}

export async function financialIntelligenceAction(form: FormData): Promise<void> {
  let space = 0;
  let tab: IntelligenceTab = "overview";
  let params: Record<string, string | number> = {};
  let outcome = "saved";
  try {
    space = record(form, "space_id");
    const action = text(form, "operation");
    const token = await getAccessToken();
    if (!token) throw new IntelligenceApiError(401, "Authentication required.");
    switch (action) {
      case "source": {
        tab = "imports";
        const result = await intelligenceApi.createSource(space, { name: text(form, "name"), population: text(form, "population"), country: text(form, "country"), lawful_basis_reference: text(form, "lawful_basis_reference") }, token);
        params = { source: result.source_id };
        break;
      }
      case "portfolio": {
        tab = "imports";
        const source = record(form, "source_id");
        params = { source };
        const controls = jsonObject(text(form, "control_totals_minor"));
        for (const [currency, amount] of Object.entries(controls)) {
          if (!/^[A-Z]{3}$/.test(currency) || (typeof amount !== "number" && typeof amount !== "string")) throw new Error("Check currency controls.");
          controls[currency] = integerInput(String(amount));
        }
        const manifest = { as_of: text(form, "as_of"), source_system: text(form, "source_name"), population: text(form, "population"),
          expected_loan_count: integerInput(text(form, "expected_loan_count"), 0, 50000), control_totals_minor: controls, financials: [] };
        const body = new FormData();
        const loans = upload(form, "loans_file", true)!;
        const schedule = upload(form, "instalments_file", false);
        if (loans.size + (schedule?.size ?? 0) > 25000000) throw new Error("Combined file size is too large.");
        body.set("loans_file", loans);
        if (schedule) body.set("instalments_file", schedule);
        body.set("manifest", JSON.stringify(manifest));
        body.set("mapping", JSON.stringify(jsonObject(optional(form, "mapping"))));
        const result = await intelligenceApi.uploadPortfolio(space, source, body, idempotency(form), token);
        params = { source, import: result.id };
        outcome = "staged";
        break;
      }
      case "review": {
        tab = "imports";
        const imported = record(form, "import_id");
        const decision = text(form, "decision");
        if (!["published", "rejected"].includes(decision)) throw new Error("Invalid review decision.");
        const result = await intelligenceApi.review(space, imported, { decision, reason: text(form, "reason") }, token);
        params = { source: result.source_id, import: imported };
        outcome = decision;
        break;
      }
      case "case": {
        tab = "cases";
        const caseId = record(form, "case_id");
        params = { case: caseId };
        const action = text(form, "case_action");
        const body: Record<string, unknown> = { action, expected_version: integerInput(text(form, "expected_version"), 1), note: text(form, "note") };
        if (action === "assign") body.assigned_to = record(form, "assigned_to");
        if (["assign", "monitor"].includes(action)) body.due_on = text(form, "due_on");
        if (action === "close") {
          body.outcome = text(form, "outcome");
          if (optional(form, "source_evidence_reference")) body.source_evidence_reference = text(form, "source_evidence_reference");
        }
        await intelligenceApi.caseEvent(space, caseId, body, idempotency(form), token);
        break;
      }
      case "grant": {
        tab = "access";
        await intelligenceApi.grant(space, { user_id: record(form, "user_id"), role: text(form, "role"), expires_at: text(form, "expires_at"), revoke: form.get("revoke") === "1" }, token);
        break;
      }
      case "freeze": {
        tab = "reports";
        const report = await intelligenceApi.freezeReport(space, record(form, "import_id"), token);
        params = { report: report.report_id };
        outcome = "frozen";
        break;
      }
      case "share": {
        tab = "reports";
        const report = record(form, "report_id");
        params = { report };
        if (form.get("confirm_sharing") !== "1") throw new Error("Confirm the scoped aggregate sharing instruction.");
        await intelligenceApi.share(space, report, { recipient_space_id: record(form, "recipient_space_id"), expires_at: text(form, "expires_at"), revoke: form.get("revoke") === "1" }, token);
        break;
      }
      case "statement": {
        tab = "statements";
        const body = new FormData();
        body.set("statement_file", upload(form, "statement_file", true)!);
        for (const field of ["issuer_version_id", "currency", "period_start", "period_end", "account_reference", "authority_reference", "authority_expires_at"]) body.set(field, text(form, field));
        for (const field of ["opening_balance_minor", "closing_balance_minor"]) {
          const amount = optional(form, field);
          if (amount !== undefined) body.set(field, String(integerInput(amount, -900000000000000)));
        }
        if (form.get("authority_confirmed") !== "1") throw new Error("Confirm your authority to submit this account.");
        body.set("authority_confirmed", "1");
        body.set("purpose", "financial_analysis");
        body.set("mapping", JSON.stringify(jsonObject(optional(form, "mapping"))));
        const statement = await intelligenceApi.uploadStatement(space, body, idempotency(form), token);
        params = { statement: statement.id };
        outcome = "uploaded";
        break;
      }
      case "revoke_statement": {
        tab = "statements";
        await intelligenceApi.revokeStatement(space, record(form, "statement_id"), token);
        outcome = "revoked";
        break;
      }
      default: throw new Error("Unsupported operation.");
    }
  } catch (error) {
    if (space === 0) redirect("/spaces");
    const kind = error instanceof IntelligenceApiError ? error.kind : "validation";
    // Never put customer identifiers, notes, statement contents or upstream diagnostics in URLs.
    redirect(destination(space, tab, { ...params, error: kind }));
  }
  redirect(destination(space, tab, { ...params, status: outcome }));
}
