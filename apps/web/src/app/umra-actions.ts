"use server";

import { redirect } from "next/navigation";
import { governanceApi } from "@/lib/api/governance";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function fail(error: unknown): never {
  const message = error instanceof Error ? error.message : "UMRA control action failed";
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  redirect("/admin/umra?error=" + encodeURIComponent(kind) + "&message=" + encodeURIComponent(message));
}

export async function retryCrbSubmissionAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await governanceApi.retryCreditReference(Number(value(formData, "submission_id")), token);
  } catch (error) {
    fail(error);
  }
  redirect("/admin/umra?status=crb-retried");
}

export async function evaluateNplAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await governanceApi.evaluateNpl(Number(value(formData, "loan_id")), token);
  } catch (error) {
    fail(error);
  }
  redirect("/admin/umra?status=npl-evaluated");
}

export async function proposeTermVariationAction(formData: FormData) {
  const token = await getAccessToken();
  let changes: Record<string, unknown>;

  try {
    const parsed = JSON.parse(value(formData, "proposed_changes"));
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      throw new Error("Proposed changes must be a JSON object.");
    }
    changes = parsed as Record<string, unknown>;
  } catch (error) {
    fail(error);
  }

  try {
    await governanceApi.proposeVariation(
      Number(value(formData, "loan_id")),
      {
        proposed_changes: changes!,
        reason: value(formData, "reason")
      },
      token
    );
  } catch (error) {
    fail(error);
  }

  redirect("/admin/umra?status=variation-proposed");
}

export async function recordUmraApprovalAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await governanceApi.recordUmraApproval(
      Number(value(formData, "variation_id")),
      {
        approval_reference: value(formData, "approval_reference"),
        approval_document_hash: value(formData, "approval_document_hash")
      },
      token
    );
  } catch (error) {
    fail(error);
  }
  redirect("/admin/umra?status=umra-approval-recorded");
}

export async function applyTermVariationAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await governanceApi.applyVariation(Number(value(formData, "variation_id")), token);
  } catch (error) {
    fail(error);
  }
  redirect("/admin/umra?status=variation-applied");
}
