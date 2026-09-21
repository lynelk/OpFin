"use server";

import { redirect } from "next/navigation";
import {
  inclusiveFinanceApi,
  type CreateInclusiveFinanceProgramme,
  type ProgrammeEligibilityRule
} from "@/lib/api/inclusive-finance";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw.trim() : "";
}

function optionalNumber(formData: FormData, key: string): number | undefined {
  const raw = value(formData, key);
  if (!raw) return undefined;
  const parsed = Number(raw);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
}

function equalsRule(field: string, raw: string): ProgrammeEligibilityRule | null {
  if (!raw || raw === "any") return null;
  if (field === "first_time_formal_borrower") {
    return { field, operator: "equals", value: raw === "yes" };
  }
  if (field === "kyc_verified") {
    return raw === "required"
      ? { field, operator: "equals", value: true }
      : null;
  }
  return { field, operator: "equals", value: raw };
}

export async function createInclusiveFinanceProgrammeAction(formData: FormData) {
  const token = await getAccessToken();
  const rules = [
    equalsRule("age_cohort", value(formData, "age_cohort")),
    equalsRule("gender", value(formData, "gender")),
    equalsRule("disability_status", value(formData, "disability_status")),
    equalsRule("refugee_or_displaced_status", value(formData, "refugee_or_displaced_status")),
    equalsRule("rural_urban", value(formData, "rural_urban")),
    equalsRule("employment_category", value(formData, "employment_category")),
    equalsRule("first_time_formal_borrower", value(formData, "first_time_formal_borrower")),
    equalsRule("kyc_verified", value(formData, "kyc_verified"))
  ].filter((rule): rule is ProgrammeEligibilityRule => rule !== null);

  const payload: CreateInclusiveFinanceProgramme = {
    code: value(formData, "code"),
    name: value(formData, "name"),
    status: (value(formData, "status") || "draft") as CreateInclusiveFinanceProgramme["status"],
    sponsor_space_id: optionalNumber(formData, "sponsor_space_id"),
    partner_id: optionalNumber(formData, "partner_id"),
    eligibility_rules: rules.length ? { all: rules } : undefined,
    starts_at: value(formData, "starts_at") || undefined,
    ends_at: value(formData, "ends_at") || undefined
  };

  try {
    await inclusiveFinanceApi.createProgramme(payload, token);
  } catch (error) {
    const message = error instanceof Error ? error.message : "Programme creation failed";
    redirect(`/admin/inclusion?error=server&message=${encodeURIComponent(message)}`);
  }

  redirect("/admin/inclusion?status=programme-created");
}
