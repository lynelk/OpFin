"use server";

import { redirect } from "next/navigation";
import { essentialsApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function numberValue(formData: FormData, key: string): number | undefined {
  const raw = value(formData, key);
  if (!raw) return undefined;
  const parsed = Number(raw.replaceAll(",", ""));
  return Number.isFinite(parsed) ? parsed : undefined;
}

function fail(error: unknown): never {
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  const message = error instanceof Error ? error.message : "Unable to complete the Essentials action.";
  redirect(`/essentials?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

export async function refreshEssentialsEligibilityAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await essentialsApi.refreshEligibility(
      {
        financial_space_id: numberValue(formData, "financial_space_id"),
        channel: "web"
      },
      token
    );
  } catch (error) {
    fail(error);
  }
  redirect("/essentials?status=eligibility-refreshed");
}

export async function addEssentialsAccountAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const billerId = numberValue(formData, "biller_id");
    const accountReference = value(formData, "account_reference");
    if (!billerId || !accountReference) throw new Error("Choose a service and enter its account reference.");

    const catalogue = await essentialsApi.catalogue(token);
    const biller = catalogue.data.billers.find((item) => item.id === billerId);
    const metadata =
      biller?.category === "rent"
        ? {
            landlord_name: value(formData, "landlord_name"),
            beneficiary_name: value(formData, "beneficiary_name"),
            beneficiary_channel: value(formData, "beneficiary_channel")
          }
        : undefined;

    await essentialsApi.addAccount(
      {
        biller_id: billerId,
        account_reference: accountReference,
        nickname: value(formData, "nickname") || undefined,
        financial_space_id: numberValue(formData, "financial_space_id"),
        metadata
      },
      token
    );
  } catch (error) {
    fail(error);
  }
  redirect("/essentials?status=account-saved");
}

export async function verifyEssentialsAccountAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const accountId = numberValue(formData, "account_id");
    if (!accountId) throw new Error("The service account could not be identified.");
    await essentialsApi.verifyAccount(accountId, token);
  } catch (error) {
    fail(error);
  }
  redirect("/essentials?status=verification-updated");
}

export async function createEssentialsQuoteAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const accountId = numberValue(formData, "account_id");
    const amountMinor = numberValue(formData, "amount_minor");
    if (!accountId || !amountMinor || amountMinor <= 0) throw new Error("Enter a valid finance amount.");
    await essentialsApi.createQuote(
      { essentials_account_id: accountId, amount_minor: amountMinor, channel: "web" },
      token
    );
  } catch (error) {
    fail(error);
  }
  redirect("/essentials?status=quote-created#offers");
}

export async function acceptEssentialsQuoteAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const quoteId = numberValue(formData, "quote_id");
    const disclosureHash = value(formData, "disclosure_hash");
    const confirmed = value(formData, "confirm_terms");
    if (!quoteId || disclosureHash.length !== 64 || confirmed !== "yes") {
      throw new Error("Review and confirm the lender and repayment terms before accepting.");
    }
    await essentialsApi.acceptQuote(quoteId, disclosureHash, token);
  } catch (error) {
    fail(error);
  }
  redirect("/essentials?status=finance-accepted");
}

export async function repayEssentialsAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const advanceId = numberValue(formData, "advance_id");
    const amountMinor = numberValue(formData, "amount_minor");
    if (!advanceId || !amountMinor || amountMinor <= 0) throw new Error("Enter a valid repayment amount.");
    await essentialsApi.repay(advanceId, amountMinor, `web-${advanceId}-${Date.now()}`, token);
  } catch (error) {
    fail(error);
  }
  redirect("/essentials?status=repayment-submitted");
}

export async function authoriseEssentialsPartnerAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const partnerAccountId = numberValue(formData, "partner_account_id");
    if (!partnerAccountId) throw new Error("Choose an approved platform.");
    await essentialsApi.authorisePartner(
      {
        partner_account_id: partnerAccountId,
        scopes: ["eligibility", "account_write", "quote_create", "status_read"],
        valid_days: 30
      },
      token
    );
  } catch (error) {
    fail(error);
  }
  redirect("/essentials?status=platform-access-granted");
}

export async function revokeEssentialsPartnerAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    const id = numberValue(formData, "authorisation_id");
    if (!id) throw new Error("The platform permission could not be identified.");
    await essentialsApi.revokePartnerAuthorisation(id, token);
  } catch (error) {
    fail(error);
  }
  redirect("/essentials?status=platform-access-revoked");
}
