"use server";

import { randomUUID } from "node:crypto";
import { redirect } from "next/navigation";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { opfinApi } from "@/lib/api/client";
import { stepUpApi } from "@/lib/api/step-up";
import { OpfinApiError } from "@/lib/api/errors";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function number(formData: FormData, key: string): number {
  const parsed = Number(value(formData, key));
  return Number.isFinite(parsed) ? parsed : 0;
}

function fail(error: unknown, destination: string): never {
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  const message = error instanceof Error ? error.message : "Unable to complete that action.";
  const separator = destination.includes("?") ? "&" : "?";
  redirect(`${destination}${separator}error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

async function sendStepUpOtp(token: string | undefined): Promise<void> {
  const profile = await opfinApi.profile(token);
  const phone = profile.data.user.phone;
  if (!phone) throw new Error("Your registered phone number is missing. Update your identity profile before investing.");
  await stepUpApi.generateOtp(phone);
}

export async function linkAccountFocusedAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.linkAccount({
      account_type: value(formData, "account_type"),
      provider: value(formData, "provider"),
      identifier: value(formData, "identifier")
    }, token);
  } catch (error) { fail(error, "/connected-accounts"); }
  redirect("/connected-accounts?status=submitted");
}

export async function saveHouseholdFocusedAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.saveHousehold({
      household_size: number(formData, "household_size"),
      monthly_income_minor: number(formData, "monthly_income_minor"),
      monthly_fixed_costs_minor: number(formData, "monthly_fixed_costs_minor"),
      emergency_target_minor: number(formData, "emergency_target_minor")
    }, token);
  } catch (error) { fail(error, "/household-finance"); }
  redirect("/household-finance?status=saved");
}

export async function saveMicrobusinessFocusedAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.saveMicrobusiness({
      business_name: value(formData, "business_name"),
      business_type: value(formData, "business_type"),
      registration_reference: value(formData, "registration_reference") || undefined,
      monthly_revenue_minor: number(formData, "monthly_revenue_minor"),
      monthly_expense_minor: number(formData, "monthly_expense_minor")
    }, token);
  } catch (error) { fail(error, "/microbusiness"); }
  redirect("/microbusiness?status=saved");
}

export async function joinCommunityFocusedAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.joinCommunity({
      institution_type: value(formData, "institution_type"),
      institution_name: value(formData, "institution_name"),
      member_reference: value(formData, "member_reference") || undefined
    }, token);
  } catch (error) { fail(error, "/community-finance"); }
  redirect("/community-finance?status=submitted");
}

export async function createReferralFocusedAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const referredUserId = number(formData, "referred_user_id");
    await longRangeApi.createReferral({ referred_user_id: referredUserId || undefined, event_type: "invited" }, token);
  } catch (error) { fail(error, "/referrals"); }
  redirect("/referrals?status=recorded");
}

export async function createPeerFundingRequestAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await longRangeApi.createParticipatoryListing({
      purpose: value(formData, "purpose"),
      target_amount_minor: number(formData, "target_amount_minor"),
      term_days: number(formData, "term_days")
    }, token);
  } catch (error) { fail(error, "/peer-lending/borrow"); }
  redirect("/peer-lending/borrow?status=submitted");
}

export async function createPeerInvestmentAction(formData: FormData) {
  const token = await getAccessToken();
  const listingId = number(formData, "listing_id");
  const amountMinor = number(formData, "amount_minor");
  let intentReference = "";

  try {
    const commitmentResult = await longRangeApi.createParticipatoryCommitment({ listing_id: listingId, amount_minor: amountMinor }, token);
    const commitmentId = Number(commitmentResult.commitment.id);
    const intentResult = await longRangeApi.createFinancialIntent({
      source_type: "participatory_commitment",
      source_id: commitmentId,
      amount_minor: amountMinor,
      idempotency_key: `p2p-${commitmentId}-${randomUUID()}`
    }, token);
    intentReference = String(intentResult.financial_intent.reference ?? "");
    if (!intentReference) throw new Error("Investment reference was not returned.");
    await sendStepUpOtp(token);
  } catch (error) { fail(error, "/peer-lending"); }

  redirect(`/peer-lending/confirm?intent=${encodeURIComponent(intentReference)}&amount=${amountMinor}`);
}

export async function resendPeerOtpAction(formData: FormData) {
  const token = await getAccessToken();
  const intent = value(formData, "intent");
  const amount = number(formData, "amount");
  const destination = `/peer-lending/confirm?intent=${encodeURIComponent(intent)}&amount=${amount}`;
  try {
    await sendStepUpOtp(token);
  } catch (error) { fail(error, destination); }
  redirect(`${destination}&status=otp-resent`);
}

export async function confirmPeerInvestmentAction(formData: FormData) {
  const token = await getAccessToken();
  const intent = value(formData, "intent");
  const amount = number(formData, "amount");
  const otp = value(formData, "otp");
  const destination = `/peer-lending/confirm?intent=${encodeURIComponent(intent)}&amount=${amount}`;
  let status = "processing";

  try {
    const profile = await opfinApi.profile(token);
    const phone = profile.data.user.phone;
    if (!phone) throw new Error("Your registered phone number is missing.");
    const verification = await stepUpApi.verifyOtp(phone, otp);
    const confirmed = await longRangeApi.confirmFinancialIntent(intent, verification.verification_token, token);
    status = String(confirmed.financial_intent.status ?? "provider_processing").replaceAll("_", "-");
  } catch (error) { fail(error, destination); }

  redirect(`/peer-lending?status=${encodeURIComponent(status)}`);
}

export async function reviewPeerListingAction(formData: FormData) {
  const token = await getAccessToken();
  const id = number(formData, "id");
  const status = value(formData, "status");
  try {
    await longRangeApi.reviewParticipatoryListing(id, {
      status,
      lender_of_record: value(formData, "lender_of_record") || undefined,
      loss_allocation: value(formData, "loss_allocation") || undefined,
      fees: value(formData, "fees") || undefined,
      custody: value(formData, "custody") || undefined,
      guarantee: value(formData, "guarantee") || undefined,
      expected_return_percent: number(formData, "expected_return_percent") || undefined,
      risk_grade: value(formData, "risk_grade") || undefined,
      risk_summary: value(formData, "risk_summary") || undefined,
      borrower_summary: value(formData, "borrower_summary") || undefined,
      repayment_frequency: value(formData, "repayment_frequency") || undefined
    }, token);
  } catch (error) { fail(error, "/admin/long-range"); }
  redirect("/admin/long-range?status=peer-review-completed");
}
