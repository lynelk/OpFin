"use server";

import { redirect } from "next/navigation";
import { saveProtectionOperationsApi } from "@/lib/api/save-protection-operations";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function optionalNumber(raw: string): number | undefined {
  if (!raw) return undefined;
  const parsed = Number(raw);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : undefined;
}

function lines(raw: string): string[] | undefined {
  const items = raw.split("\n").map((item) => item.trim()).filter(Boolean);
  return items.length ? items : undefined;
}

function redirectWith(params: Record<string, string>): never {
  redirect(`/admin/save-protection?${new URLSearchParams(params).toString()}`);
}

function actionError(error: unknown, fallback: string): never {
  if (error instanceof OpfinApiError) {
    redirectWith({ error: error.kind, message: error.message });
  }

  redirectWith({
    error: "server",
    message: error instanceof Error ? error.message : fallback
  });
}

export async function createSavingsProductAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.createSavingsProduct({
      code: value(formData, "code"),
      name: value(formData, "name"),
      partner_name: value(formData, "partner_name"),
      partner_product_reference: value(formData, "partner_product_reference") || undefined,
      country_code: value(formData, "country_code") || "UG",
      currency: value(formData, "currency") || "UGX",
      product_type: value(formData, "product_type") as "goal" | "emergency" | "notice" | "group" | "sacco" | "employer",
      custody_model: "partner_held",
      minimum_contribution_minor: optionalNumber(value(formData, "minimum_contribution_minor")),
      maximum_contribution_minor: optionalNumber(value(formData, "maximum_contribution_minor")),
      minimum_withdrawal_minor: optionalNumber(value(formData, "minimum_withdrawal_minor")),
      notice_days: optionalNumber(value(formData, "notice_days")),
      lock_days: optionalNumber(value(formData, "lock_days")),
      terms_version: value(formData, "terms_version"),
      terms_url: value(formData, "terms_url") || undefined,
      disclosures: lines(value(formData, "disclosures"))
    }, token);
  } catch (error) {
    actionError(error, "Unable to create savings product draft.");
  }

  redirectWith({ status: "savings-product-created" });
}

export async function activateSavingsProductAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.activateSavingsProduct(Number(value(formData, "product_id")), {
      approval_reference: value(formData, "approval_reference"),
      approval_evidence_hash: value(formData, "approval_evidence_hash"),
      approval_note: value(formData, "approval_note")
    }, token);
  } catch (error) {
    actionError(error, "Unable to activate savings product.");
  }

  redirectWith({ status: "savings-product-activated" });
}

export async function confirmSavingsContributionAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.confirmSavingsContribution(Number(value(formData, "movement_id")), {
      partner_reference: value(formData, "partner_reference"),
      partner_evidence_hash: value(formData, "partner_evidence_hash")
    }, token);
  } catch (error) {
    actionError(error, "Unable to confirm savings contribution.");
  }

  redirectWith({ status: "savings-contribution-confirmed" });
}

export async function releaseSavingsWithdrawalAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.releaseSavingsWithdrawal(Number(value(formData, "movement_id")), {
      partner_reference: value(formData, "partner_reference"),
      partner_evidence_hash: value(formData, "partner_evidence_hash")
    }, token);
  } catch (error) {
    actionError(error, "Unable to release savings withdrawal.");
  }

  redirectWith({ status: "savings-withdrawal-released" });
}

export async function retrySavingsWithdrawalPayoutAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.retrySavingsWithdrawalPayout(Number(value(formData, "movement_id")), token);
  } catch (error) {
    actionError(error, "Unable to retry savings withdrawal payout.");
  }

  redirectWith({ status: "savings-payout-retried" });
}

export async function createProtectionProductAction(formData: FormData) {
  const token = await getAccessToken();
  const claimsDisclosure = value(formData, "claims_disclosure");

  try {
    await saveProtectionOperationsApi.createProtectionProduct({
      code: value(formData, "code"),
      name: value(formData, "name"),
      insurer_name: value(formData, "insurer_name"),
      underwriter_name: value(formData, "underwriter_name") || undefined,
      partner_product_reference: value(formData, "partner_product_reference") || undefined,
      country_code: value(formData, "country_code") || "UG",
      currency: value(formData, "currency") || "UGX",
      product_type: value(formData, "product_type") as "micro" | "loan" | "health" | "event" | "device" | "asset",
      premium_amount_minor: Number(value(formData, "premium_amount_minor")),
      premium_frequency: value(formData, "premium_frequency") as "weekly" | "monthly" | "quarterly" | "annual" | "yearly" | "one_off" | "single",
      coverage_limit_minor: optionalNumber(value(formData, "coverage_limit_minor")),
      disclosure_version: value(formData, "disclosure_version"),
      benefits: lines(value(formData, "benefits")),
      exclusions: lines(value(formData, "exclusions")) ?? [],
      disclosure_payload: {
        decision_authority: "insurer_or_underwriter",
        claims: claimsDisclosure
      },
      terms_url: value(formData, "terms_url") || undefined
    }, token);
  } catch (error) {
    actionError(error, "Unable to create protection product draft.");
  }

  redirectWith({ status: "protection-product-created" });
}

export async function activateProtectionProductAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.activateProtectionProduct(Number(value(formData, "product_id")), {
      approval_reference: value(formData, "approval_reference"),
      approval_evidence_hash: value(formData, "approval_evidence_hash"),
      approval_note: value(formData, "approval_note")
    }, token);
  } catch (error) {
    actionError(error, "Unable to activate protection product.");
  }

  redirectWith({ status: "protection-product-activated" });
}

export async function confirmProtectionPremiumAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.confirmProtectionPremium(Number(value(formData, "payment_id")), {
      partner_reference: value(formData, "partner_reference"),
      partner_evidence_hash: value(formData, "partner_evidence_hash")
    }, token);
  } catch (error) {
    actionError(error, "Unable to confirm insurer premium settlement.");
  }

  redirectWith({ status: "protection-premium-confirmed" });
}

export async function issueProtectionPolicyAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.issueProtectionPolicy(Number(value(formData, "policy_id")), {
      external_policy_number: value(formData, "external_policy_number"),
      partner_reference: value(formData, "partner_reference"),
      cover_start_date: value(formData, "cover_start_date"),
      cover_end_date: value(formData, "cover_end_date")
    }, token);
  } catch (error) {
    actionError(error, "Unable to record insurer policy issuance.");
  }

  redirectWith({ status: "protection-policy-issued" });
}

export async function updateProtectionClaimAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await saveProtectionOperationsApi.updateProtectionClaim(Number(value(formData, "claim_id")), {
      status: value(formData, "status"),
      partner_claim_reference: value(formData, "partner_claim_reference") || undefined,
      decision_reason: value(formData, "decision_reason") || undefined,
      approved_amount_minor: optionalNumber(value(formData, "approved_amount_minor"))
    }, token);
  } catch (error) {
    actionError(error, "Unable to update insurer claim state.");
  }

  redirectWith({ status: "protection-claim-updated" });
}
