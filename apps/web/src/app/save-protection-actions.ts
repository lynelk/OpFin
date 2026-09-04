"use server";

import { randomUUID } from "node:crypto";
import { redirect } from "next/navigation";
import { saveProtectionApi } from "@/lib/api/save-protection";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function optionalNumber(raw: string): number | undefined {
  if (!raw) return undefined;
  const parsed = Number(raw);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined;
}

function redirectWith(path: string, params: Record<string, string>): never {
  const search = new URLSearchParams(params);
  redirect(`${path}?${search.toString()}`);
}

function actionError(error: unknown, path: string, fallback: string): never {
  if (error instanceof OpfinApiError) {
    redirectWith(path, { error: error.kind, message: error.message });
  }

  redirectWith(path, {
    error: "server",
    message: error instanceof Error ? error.message : fallback
  });
}

export async function createSavingsGoalAction(formData: FormData) {
  const token = await getAccessToken();
  const payload = {
    savings_product_id: Number(value(formData, "savings_product_id")),
    name: value(formData, "name"),
    target_amount_minor: optionalNumber(value(formData, "target_amount_minor")),
    target_date: value(formData, "target_date") || undefined,
    scheduled_amount_minor: optionalNumber(value(formData, "scheduled_amount_minor")),
    contribution_frequency: value(formData, "contribution_frequency") || undefined
  };

  let goalId: number;
  try {
    const response = await saveProtectionApi.createSavingsGoal(payload, token);
    goalId = response.data.goal.id;
  } catch (error) {
    actionError(error, "/savings", "Unable to create savings goal.");
  }

  redirect(`/savings/${goalId}?status=created`);
}

export async function updateSavingsScheduleAction(formData: FormData) {
  const token = await getAccessToken();
  const goalId = Number(value(formData, "goal_id"));

  try {
    await saveProtectionApi.updateSavingsSchedule(goalId, {
      scheduled_amount_minor: optionalNumber(value(formData, "scheduled_amount_minor")),
      contribution_frequency: value(formData, "contribution_frequency") || undefined,
      autopilot_enabled: false
    }, token);
  } catch (error) {
    actionError(error, `/savings/${goalId}`, "Unable to update savings schedule.");
  }

  redirect(`/savings/${goalId}?status=schedule-updated`);
}

export async function pauseSavingsGoalAction(formData: FormData) {
  const token = await getAccessToken();
  const goalId = Number(value(formData, "goal_id"));

  try {
    await saveProtectionApi.pauseSavingsGoal(goalId, token);
  } catch (error) {
    actionError(error, `/savings/${goalId}`, "Unable to pause savings goal.");
  }

  redirect(`/savings/${goalId}?status=paused`);
}

export async function resumeSavingsGoalAction(formData: FormData) {
  const token = await getAccessToken();
  const goalId = Number(value(formData, "goal_id"));

  try {
    await saveProtectionApi.resumeSavingsGoal(goalId, token);
  } catch (error) {
    actionError(error, `/savings/${goalId}`, "Unable to resume savings goal.");
  }

  redirect(`/savings/${goalId}?status=resumed`);
}

export async function contributeSavingsAction(formData: FormData) {
  const token = await getAccessToken();
  const goalId = Number(value(formData, "goal_id"));

  try {
    await saveProtectionApi.contributeSavings(goalId, {
      amount_minor: Number(value(formData, "amount_minor")),
      idempotency_key: value(formData, "idempotency_key") || randomUUID()
    }, token);
  } catch (error) {
    actionError(error, `/savings/${goalId}`, "Unable to initiate savings contribution.");
  }

  redirect(`/savings/${goalId}?status=contribution-pending`);
}

export async function withdrawSavingsAction(formData: FormData) {
  const token = await getAccessToken();
  const goalId = Number(value(formData, "goal_id"));

  try {
    await saveProtectionApi.withdrawSavings(goalId, {
      amount_minor: Number(value(formData, "amount_minor")),
      idempotency_key: value(formData, "idempotency_key") || randomUUID()
    }, token);
  } catch (error) {
    actionError(error, `/savings/${goalId}`, "Unable to request savings withdrawal.");
  }

  redirect(`/savings/${goalId}?status=withdrawal-requested`);
}

export async function enrollProtectionAction(formData: FormData) {
  const token = await getAccessToken();
  const productId = Number(value(formData, "product_id"));
  const accepted = value(formData, "accept_disclosures");

  if (!accepted) {
    redirectWith("/insurance", {
      error: "validation",
      message: "Review and explicitly accept the insurer disclosures before enrolling."
    });
  }

  let policyId: number;
  try {
    const response = await saveProtectionApi.enrollProtection(productId, {
      accept_disclosures: true,
      disclosure_hash: value(formData, "disclosure_hash")
    }, token);
    policyId = response.data.policy.id;
  } catch (error) {
    actionError(error, "/insurance", "Unable to enroll in protection product.");
  }

  redirect(`/insurance/${policyId}?status=enrolled`);
}

export async function payProtectionPremiumAction(formData: FormData) {
  const token = await getAccessToken();
  const policyId = Number(value(formData, "policy_id"));

  try {
    await saveProtectionApi.payProtectionPremium(policyId, {
      idempotency_key: value(formData, "idempotency_key") || randomUUID()
    }, token);
  } catch (error) {
    actionError(error, `/insurance/${policyId}`, "Unable to initiate protection premium collection.");
  }

  redirect(`/insurance/${policyId}?status=premium-pending`);
}

export async function submitProtectionClaimAction(formData: FormData) {
  const token = await getAccessToken();
  const policyId = Number(value(formData, "policy_id"));
  const evidence = value(formData, "evidence")
    .split("\n")
    .map((item) => item.trim())
    .filter(Boolean);

  try {
    await saveProtectionApi.submitProtectionClaim(policyId, {
      incident_date: value(formData, "incident_date"),
      category: value(formData, "category"),
      description: value(formData, "description"),
      claimed_amount_minor: optionalNumber(value(formData, "claimed_amount_minor")),
      evidence: evidence.length ? evidence : undefined
    }, token);
  } catch (error) {
    actionError(error, `/insurance/${policyId}`, "Unable to submit protection claim.");
  }

  redirect(`/insurance/${policyId}?status=claim-submitted`);
}

export async function disputeProtectionClaimAction(formData: FormData) {
  const token = await getAccessToken();
  const policyId = Number(value(formData, "policy_id"));
  const claimId = Number(value(formData, "claim_id"));

  try {
    await saveProtectionApi.disputeProtectionClaim(claimId, {
      reason: value(formData, "reason")
    }, token);
  } catch (error) {
    actionError(error, `/insurance/${policyId}`, "Unable to open protection claim dispute.");
  }

  redirect(`/insurance/${policyId}?status=claim-disputed`);
}
