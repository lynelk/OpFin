"use server";

import { redirect } from "next/navigation";
import { v5P0Api } from "@/lib/api/v5-p0";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function amount(formData: FormData, key: string): number {
  const parsed = Number(value(formData, key));
  if (!Number.isFinite(parsed) || parsed < 0) throw new Error("Enter a valid non-negative amount.");
  return Math.round(parsed);
}

function integer(formData: FormData, key: string): number {
  const parsed = Number(value(formData, key));
  if (!Number.isInteger(parsed) || parsed <= 0) throw new Error(`${key} must be a positive integer.`);
  return parsed;
}

function json(formData: FormData, key: string, fallback: unknown = {}): unknown {
  const raw = value(formData, key);
  if (!raw) return fallback;
  try {
    return JSON.parse(raw);
  } catch {
    throw new Error(`${key} must contain valid JSON.`);
  }
}

function fail(error: unknown, destination: string): never {
  const kind = error instanceof OpfinApiError ? error.kind : "validation";
  const message = error instanceof Error ? error.message : "Unable to complete the request.";
  redirect(`${destination}?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

export async function updateSecurityCentreAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.updateSecurity({
      transactions_frozen: value(formData, "transactions_frozen") === "true",
      login_alerts: value(formData, "login_alerts") === "true",
      payment_alerts: value(formData, "payment_alerts") === "true"
    }, token);
  } catch (error) {
    fail(error, "/security");
  }
  redirect("/security?status=updated");
}

export async function saveCreditBuilderAction(formData: FormData) {
  const token = await getAccessToken();
  const target = value(formData, "target_score");
  try {
    await v5P0Api.saveCreditBuilder({
      goal: value(formData, "goal") || null,
      target_score: target ? Number(target) : null,
      actions: value(formData, "actions").split("\n").map((item) => item.trim()).filter(Boolean),
      review_due_at: value(formData, "review_due_at") || null
    }, token);
  } catch (error) {
    fail(error, "/credit-builder");
  }
  redirect("/credit-builder?status=saved");
}

export async function openHardshipAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.openHardship({
      reason: value(formData, "reason"),
      monthly_income_minor: amount(formData, "monthly_income_minor"),
      essential_expenses_minor: amount(formData, "essential_expenses_minor"),
      debt_commitments_minor: amount(formData, "debt_commitments_minor"),
      requested_relief: value(formData, "requested_relief").split("\n").map((item) => item.trim()).filter(Boolean)
    }, token);
  } catch (error) {
    fail(error, "/hardship");
  }
  redirect("/hardship?status=submitted");
}

export async function approveHardshipAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.approveHardship(integer(formData, "case_id"), json(formData, "approved_relief", []), token);
  } catch (error) {
    fail(error, "/admin/platform-governance");
  }
  redirect("/admin/platform-governance?status=hardship-approved");
}

export async function createProductDefinitionAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.createProduct({
      product_code: value(formData, "product_code"),
      name: value(formData, "name"),
      definition: json(formData, "definition")
    }, token);
  } catch (error) {
    fail(error, "/admin/platform-governance");
  }
  redirect("/admin/platform-governance?status=product-created");
}

export async function transitionProductDefinitionAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.transitionProduct(integer(formData, "product_id"), value(formData, "status"), token);
  } catch (error) {
    fail(error, "/admin/platform-governance");
  }
  redirect("/admin/platform-governance?status=product-transitioned");
}

export async function createDecisionRuleAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.createRule({
      rule_code: value(formData, "rule_code"),
      name: value(formData, "name"),
      priority: Number(value(formData, "priority") || "100"),
      conditions: json(formData, "conditions", []),
      actions: json(formData, "actions", [])
    }, token);
  } catch (error) {
    fail(error, "/admin/platform-governance");
  }
  redirect("/admin/platform-governance?status=rule-created");
}

export async function approveDecisionRuleAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.approveRule(integer(formData, "rule_id"), token);
  } catch (error) {
    fail(error, "/admin/platform-governance");
  }
  redirect("/admin/platform-governance?status=rule-approved");
}

export async function createWorkflowDefinitionAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.createWorkflow({
      workflow_code: value(formData, "workflow_code"),
      name: value(formData, "name"),
      states: json(formData, "states", []),
      transitions: json(formData, "transitions", [])
    }, token);
  } catch (error) {
    fail(error, "/admin/platform-governance");
  }
  redirect("/admin/platform-governance?status=workflow-created");
}

export async function approveWorkflowDefinitionAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await v5P0Api.approveWorkflow(integer(formData, "workflow_id"), token);
  } catch (error) {
    fail(error, "/admin/platform-governance");
  }
  redirect("/admin/platform-governance?status=workflow-approved");
}
