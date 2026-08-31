"use server";

import { redirect } from "next/navigation";
import { governanceApi } from "@/lib/api/governance";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw : "";
}

function redirectError(error: unknown): never {
  const message = error instanceof Error ? error.message : "Governance action failed";
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  redirect(`/admin/compliance?error=${encodeURIComponent(kind)}&message=${encodeURIComponent(message)}`);
}

export async function generateRegulatoryReportAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await governanceApi.generateReport({
      report_type: value(formData, "report_type"),
      period_start: value(formData, "period_start"),
      period_end: value(formData, "period_end")
    }, token);
  } catch (error) {
    redirectError(error);
  }
  redirect("/admin/compliance?status=regulatory-generated");
}

export async function approveRegulatoryReportAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await governanceApi.approveReport(Number(value(formData, "report_id")), token);
  } catch (error) {
    redirectError(error);
  }
  redirect("/admin/compliance?status=regulatory-approved");
}

export async function runFinancialIntegrityAction() {
  const token = await getAccessToken();
  try {
    await governanceApi.runIntegrity(token);
  } catch (error) {
    redirectError(error);
  }
  redirect("/admin/compliance?status=integrity-run");
}

export async function resolveIntegrityAlertAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await governanceApi.resolveIntegrityAlert(Number(value(formData, "alert_id")), value(formData, "resolution"), token);
  } catch (error) {
    redirectError(error);
  }
  redirect("/admin/compliance?status=integrity-resolved");
}
