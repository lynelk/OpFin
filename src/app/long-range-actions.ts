"use server";

import { redirect } from "next/navigation";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function number(formData: FormData, key: string): number {
  const parsed = Number(value(formData, key));
  return Number.isFinite(parsed) ? parsed : 0;
}

function jsonValue(formData: FormData, key: string, fallback: unknown = []): unknown {
  const raw = value(formData, key);
  if (!raw) return fallback;
  try { return JSON.parse(raw); } catch { return fallback; }
}

function fail(error: unknown, destination = "/ecosystem"): never {
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  const message = error instanceof Error ? error.message : "Action failed";
  redirect(`${destination}?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

export async function linkFinancialAccountAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.linkAccount({
      account_type: value(formData, "account_type"), provider: value(formData, "provider"), identifier: value(formData, "identifier"),
      consent_reference: value(formData, "consent_reference") || undefined
    }, token);
  } catch (error) { fail(error); }
  redirect("/ecosystem?status=account-linked");
}

export async function saveHouseholdAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.saveHousehold({
      household_size: number(formData, "household_size"), monthly_income_minor: number(formData, "monthly_income_minor"),
      monthly_fixed_costs_minor: number(formData, "monthly_fixed_costs_minor"), emergency_target_minor: number(formData, "emergency_target_minor"),
      shared_goals: jsonValue(formData, "shared_goals", []), dependants: jsonValue(formData, "dependants", [])
    }, token);
  } catch (error) { fail(error); }
  redirect("/ecosystem?status=household-saved");
}

export async function saveMicrobusinessAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.saveMicrobusiness({
      business_name: value(formData, "business_name"), business_type: value(formData, "business_type"),
      registration_reference: value(formData, "registration_reference") || undefined,
      monthly_revenue_minor: number(formData, "monthly_revenue_minor"), monthly_expense_minor: number(formData, "monthly_expense_minor")
    }, token);
  } catch (error) { fail(error); }
  redirect("/ecosystem?status=microbusiness-saved");
}

export async function requestAssetFinanceAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.requestAssetFinance({
      asset_category: value(formData, "asset_category"), asset_description: value(formData, "asset_description"),
      asset_price_minor: number(formData, "asset_price_minor"), deposit_minor: number(formData, "deposit_minor"),
      requested_term_months: number(formData, "requested_term_months"), geolocation_consent: value(formData, "geolocation_consent") === "true"
    }, token);
  } catch (error) { fail(error); }
  redirect("/ecosystem?status=asset-finance-submitted");
}

export async function joinCommunityAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.joinCommunity({
      institution_type: value(formData, "institution_type"), institution_name: value(formData, "institution_name"),
      member_reference: value(formData, "member_reference") || undefined
    }, token);
  } catch (error) { fail(error); }
  redirect("/ecosystem?status=community-added");
}

export async function createParticipatoryListingAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.createParticipatoryListing({
      purpose: value(formData, "purpose"), target_amount_minor: number(formData, "target_amount_minor"), term_days: number(formData, "term_days"),
      lender_of_record: value(formData, "lender_of_record") || undefined, loss_allocation: value(formData, "loss_allocation") || undefined,
      fees: value(formData, "fees") || undefined, guarantee: value(formData, "guarantee") || undefined, custody: value(formData, "custody") || undefined
    }, token);
  } catch (error) { fail(error); }
  redirect("/ecosystem?status=participatory-listing-submitted");
}

export async function createReferralAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.createReferral({ referred_user_id: number(formData, "referred_user_id") || undefined, event_type: "invited" }, token);
  } catch (error) { fail(error); }
  redirect("/ecosystem?status=referral-recorded");
}

export async function reviewLongRangeAction(formData: FormData) {
  const token = await getAccessToken();
  const type = value(formData, "type");
  const id = number(formData, "id");
  const status = value(formData, "status");
  try {
    if (type === "linked_account") await longRangeApi.reviewLinkedAccount(id, { status, evidence: value(formData, "evidence") || undefined }, token);
    else if (type === "asset_finance") await longRangeApi.decideAssetFinance(id, { status, reason: value(formData, "reason") || "Reviewed by operations", approved_amount_minor: number(formData, "approved_amount_minor") || undefined }, token);
    else if (type === "community") await longRangeApi.reviewCommunity(id, { status, evidence: value(formData, "evidence") || undefined }, token);
    else if (type === "participatory") await longRangeApi.reviewParticipatoryListing(id, { status }, token);
    else if (type === "capital") await longRangeApi.reviewCapitalMandate(id, { status }, token);
    else if (type === "partner") await longRangeApi.reviewPartner(id, { status }, token);
    else if (type === "referral") await longRangeApi.approveReferralReward(id, number(formData, "reward_minor"), token);
    else throw new Error("Unsupported governance action");
  } catch (error) { fail(error, "/admin/long-range"); }
  redirect("/admin/long-range?status=review-completed");
}
