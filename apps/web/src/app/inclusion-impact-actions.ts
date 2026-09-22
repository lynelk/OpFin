"use server";

import { redirect } from "next/navigation";
import {
  inclusiveImpactApi,
  type CreateImpactIndicator,
  type UpdateProgrammeTheory
} from "@/lib/api/inclusive-impact";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  return formData.get(key)?.toString().trim() ?? "";
}

function requiredProgrammeId(formData: FormData): number {
  const parsed = Number(value(formData, "programme_id"));
  if (!Number.isInteger(parsed) || parsed <= 0) {
    throw new Error("A programme must be selected.");
  }
  return parsed;
}

function lines(formData: FormData, key: string): string[] {
  return value(formData, key)
    .split(/\r?\n/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function redirectError(programmeId: number, error: unknown): never {
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  const message = error instanceof Error ? error.message : "Impact framework update failed";
  redirect(
    `/admin/inclusion/framework?programme_id=${programmeId}&error=${kind}&message=${encodeURIComponent(message)}`
  );
}

export async function createImpactIndicatorAction(formData: FormData) {
  const programmeId = requiredProgrammeId(formData);
  const token = await getAccessToken();
  const payload: CreateImpactIndicator = {
    code: value(formData, "code"),
    name: value(formData, "name"),
    description: value(formData, "description") || undefined,
    outcome_domain: value(formData, "outcome_domain"),
    value_type: value(formData, "value_type"),
    unit: value(formData, "unit") || undefined,
    calculation_methodology: value(formData, "calculation_methodology") || undefined,
    verification_required: formData.get("verification_required") === "on",
    baseline_required: formData.get("baseline_required") === "on",
    frequency: value(formData, "frequency") || undefined,
    privacy_classification:
      (value(formData, "privacy_classification") as CreateImpactIndicator["privacy_classification"]) ||
      "programme_measurement",
    framework: value(formData, "framework") || "opfin_impact",
    framework_version: value(formData, "framework_version") || "1.0"
  };

  try {
    await inclusiveImpactApi.createIndicator(payload, token);
  } catch (error) {
    redirectError(programmeId, error);
  }

  redirect(
    `/admin/inclusion/framework?programme_id=${programmeId}&status=indicator-created`
  );
}

export async function updateProgrammeTheoryAction(formData: FormData) {
  const programmeId = requiredProgrammeId(formData);
  const token = await getAccessToken();
  const payload: UpdateProgrammeTheory = {
    problem_statement: value(formData, "problem_statement") || undefined,
    inputs: lines(formData, "inputs"),
    interventions: lines(formData, "interventions"),
    outputs: lines(formData, "outputs"),
    outcomes: lines(formData, "outcomes"),
    impact: lines(formData, "impact"),
    assumptions: lines(formData, "assumptions"),
    risks: lines(formData, "risks"),
    evidence_sources: lines(formData, "evidence_sources"),
    version: value(formData, "version") || "1.0",
    status: (value(formData, "theory_status") || "draft") as UpdateProgrammeTheory["status"]
  };

  try {
    await inclusiveImpactApi.updateProgrammeTheory(programmeId, payload, token);
  } catch (error) {
    redirectError(programmeId, error);
  }

  redirect(
    `/admin/inclusion/framework?programme_id=${programmeId}&status=theory-updated`
  );
}

export async function assignImpactIndicatorAction(formData: FormData) {
  const programmeId = requiredProgrammeId(formData);
  const token = await getAccessToken();
  const indicatorDefinitionId = Number(value(formData, "indicator_definition_id"));
  const targetNumericRaw = value(formData, "target_numeric");
  const targetNumeric = targetNumericRaw ? Number(targetNumericRaw) : undefined;

  try {
    await inclusiveImpactApi.assignIndicator(
      programmeId,
      {
        indicator_definition_id: indicatorDefinitionId,
        target_numeric:
          targetNumeric !== undefined && Number.isFinite(targetNumeric)
            ? targetNumeric
            : undefined,
        target_text: value(formData, "target_text") || undefined,
        reporting_frequency: value(formData, "reporting_frequency") || undefined,
        baseline_required: formData.get("baseline_required") === "on"
      },
      token
    );
  } catch (error) {
    redirectError(programmeId, error);
  }

  redirect(
    `/admin/inclusion/framework?programme_id=${programmeId}&status=indicator-assigned`
  );
}
