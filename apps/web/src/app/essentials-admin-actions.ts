"use server";

import { redirect } from "next/navigation";
import { essentialsApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function num(formData: FormData, key: string): number | undefined {
  const raw = value(formData, key);
  if (!raw) return undefined;
  const parsed = Number(raw.replaceAll(",", ""));
  return Number.isFinite(parsed) ? parsed : undefined;
}

function fail(error: unknown): never {
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  const message = error instanceof Error ? error.message : "Unable to complete the Essentials operations action.";
  redirect(`/admin/essentials?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

export async function saveEssentialsBillerAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await essentialsApi.adminSaveBiller(
      {
        code: value(formData, "code"),
        name: value(formData, "name"),
        category: value(formData, "category"),
        account_label: value(formData, "account_label"),
        route: value(formData, "route"),
        status: "active"
      },
      token
    );
  } catch (error) {
    fail(error);
  }
  redirect("/admin/essentials?status=biller-saved");
}

export async function saveEssentialsLenderAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const categories = value(formData, "categories")
      .split(",")
      .map((item) => item.trim().toLowerCase())
      .filter(Boolean);
    const decisionRoute = value(formData, "decision_route");
    const fundingPoolId = num(formData, "funding_pool_id");
    const maxLimit = num(formData, "max_limit_minor");
    const minLimit = num(formData, "min_limit_minor");
    const minScore = num(formData, "min_score");
    const minCoverage = num(formData, "min_coverage_percent");
    const termDays = num(formData, "term_days");
    const rate = num(formData, "monthly_interest_rate_percent");
    const fixedFee = num(formData, "fixed_fee_minor");
    const feePercent = num(formData, "fee_percent");

    if (!value(formData, "partner_code") || !value(formData, "partner_name")) throw new Error("Lender code and name are required.");
    if (!value(formData, "licence_number") || !value(formData, "licence_authority")) throw new Error("Active lenders require licensing evidence.");
    if (!termDays || termDays < 1) throw new Error("Enter the lender product term in days.");
    if (decisionRoute === "capital_mandate" && !fundingPoolId) throw new Error("Choose an approved funding pool for a capital-mandate route.");

    await essentialsApi.adminSaveLender(
      {
        partner_code: value(formData, "partner_code"),
        partner_name: value(formData, "partner_name"),
        partner_type: value(formData, "partner_type") || "financial_institution",
        regulatory_evidence: {
          licence_number: value(formData, "licence_number"),
          licence_authority: value(formData, "licence_authority")
        },
        status: "active",
        product_code: value(formData, "product_code"),
        product_name: value(formData, "product_name"),
        product_type: value(formData, "product_type") || "essentials_credit",
        eligibility_rules: {
          categories,
          min_score: minScore ?? 0,
          min_coverage_percent: minCoverage ?? 0,
          min_limit_minor: minLimit ?? 1,
          max_limit_minor: maxLimit ?? 500000,
          line_valid_days: num(formData, "line_valid_days") ?? 30
        },
        pricing: {
          term_days: termDays,
          monthly_interest_rate_percent: rate ?? 0,
          fixed_fee_minor: fixedFee ?? 0,
          fee_percent: feePercent ?? 0,
          opfin_servicing_fee_minor: num(formData, "opfin_servicing_fee_minor") ?? 0,
          partner_commission_minor: num(formData, "partner_commission_minor") ?? 0
        },
        decision_route: decisionRoute,
        funding_pool_id: fundingPoolId
      },
      token
    );
  } catch (error) {
    fail(error);
  }
  redirect("/admin/essentials?status=lender-saved");
}

export async function verifyEssentialsAccountAdminAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const accountId = num(formData, "account_id");
    if (!accountId) throw new Error("The account could not be identified.");
    const status = value(formData, "status");
    if (status !== "verified" && status !== "failed") throw new Error("Choose a valid verification outcome.");
    await essentialsApi.adminVerifyAccount(
      accountId,
      {
        status,
        provider_reference: value(formData, "provider_reference") || undefined
      },
      token
    );
  } catch (error) {
    fail(error);
  }
  redirect("/admin/essentials?status=verification-updated");
}

export async function reconcileEssentialsAdvanceAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const advanceId = num(formData, "advance_id");
    if (!advanceId) throw new Error("The advance could not be identified.");
    await essentialsApi.adminReconcileAdvance(advanceId, token);
  } catch (error) {
    fail(error);
  }
  redirect("/admin/essentials?status=reconciled");
}
