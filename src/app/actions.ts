"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  const raw = formData.get(key);
  return typeof raw === "string" ? raw : "";
}

function redirectWith(path: string, params: Record<string, string>) {
  const search = new URLSearchParams(params);
  redirect(`${path}?${search.toString()}`);
}

export async function loginAction(formData: FormData) {
  const phone = value(formData, "phone");
  const password = value(formData, "password");
  const next = value(formData, "next") || "/dashboard";

  try {
    const response = await opfinApi.login(phone, password);
    const data = response.data;
    const cookieStore = await cookies();

    cookieStore.set("opfin_access_token", data.access_token, {
      httpOnly: true,
      sameSite: "lax",
      path: "/"
    });
    cookieStore.set("opfin_role", data.user.role, {
      httpOnly: true,
      sameSite: "lax",
      path: "/"
    });
    cookieStore.set("opfin_name", encodeURIComponent(data.user.name), {
      httpOnly: true,
      sameSite: "lax",
      path: "/"
    });
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/login", { error: error.kind, message: error.message, next });
    }

    redirectWith("/login", { error: "server", message: "Login failed", next });
  }

  redirect(next);
}

export async function logoutAction() {
  const cookieStore = await cookies();
  cookieStore.delete("opfin_access_token");
  cookieStore.delete("opfin_role");
  cookieStore.delete("opfin_name");
  cookieStore.delete("opfin_demo_consent");

  redirect("/login");
}

export async function createConsentAction() {
  const token = await getAccessToken();

  try {
    await opfinApi.grantConsent({
      purpose: "credit_processing",
      policy_version: "credit-consent-v1",
      channel: "web"
    }, token);
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/consent", { error: error.kind, message: error.message });
    }

    redirectWith("/consent", { error: "server", message: "Consent creation failed" });
  }

  redirect("/consent?status=granted");
}

export async function revokeConsentAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await opfinApi.revokeConsent(Number(value(formData, "consent_id")), token);
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/consent", { error: error.kind, message: error.message });
    }

    redirectWith("/consent", { error: "server", message: "Consent revocation failed" });
  }

  redirect("/consent?status=revoked");
}

export async function submitKycCaseAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await opfinApi.submitKycCase({
      national_id: value(formData, "national_id"),
      provider: "manual",
      evidence: {
        source: "customer_portal",
        document_type: value(formData, "document_type") || "national_id"
      }
    }, token);
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/kyc", { error: error.kind, message: error.message });
    }

    redirectWith("/kyc", { error: "server", message: "KYC submission failed" });
  }

  redirect("/kyc?status=submitted");
}

export async function submitLoanApplicationAction(formData: FormData) {
  const token = await getAccessToken();
  let applicationId: number | undefined;

  try {
    const response = await opfinApi.submitDemoLoanApplication({
      loan_product_id: Number(value(formData, "loan_product_id")),
      loan_product_term_id: Number(value(formData, "loan_product_term_id")),
      institution_id: Number(value(formData, "institution_id")),
      amount: Number(value(formData, "amount")),
      reason: value(formData, "reason")
    }, token);

    applicationId = response.data.application.id;
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/loans/apply", { error: error.kind, message: error.message });
    }

    redirectWith("/loans/apply", { error: "server", message: "Loan application failed" });
  }

  redirect(applicationId ? `/loans/decision?application=${applicationId}&status=submitted` : "/loans/decision?status=submitted");
}

export async function acceptDemoOfferAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await opfinApi.acceptDemoOffer(Number(value(formData, "offer_id")), token);
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/loans/offer", { error: error.kind, message: error.message });
    }

    redirectWith("/loans/offer", { error: "server", message: "Offer acceptance failed" });
  }

  redirect("/loans/account?status=accepted");
}

export async function updateLoanApplicationStatusAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await opfinApi.updateLoanApplicationStatus(
      Number(value(formData, "application_id")),
      value(formData, "status"),
      token
    );
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/admin/credit-review", { error: error.kind, message: error.message });
    }

    redirectWith("/admin/credit-review", { error: "server", message: "Application review failed" });
  }

  redirect("/admin/credit-review?status=updated");
}

export async function createReconciliationRunAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await opfinApi.createReconciliationRun({
      provider: value(formData, "provider"),
      business_date: value(formData, "business_date")
    }, token);
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/admin/reconciliation", { error: error.kind, message: error.message });
    }

    redirectWith("/admin/reconciliation", { error: "server", message: "Reconciliation run creation failed" });
  }

  redirect("/admin/reconciliation?status=created");
}

export async function createSupportCaseAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await opfinApi.createSupportCase({
      customer_id: Number(value(formData, "customer_id")),
      category: value(formData, "category"),
      priority: value(formData, "priority") || "normal",
      subject: value(formData, "subject"),
      description: value(formData, "description")
    }, token);
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/admin/support", { error: error.kind, message: error.message });
    }

    redirectWith("/admin/support", { error: "server", message: "Support case creation failed" });
  }

  redirect("/admin/support?status=created");
}

export async function createComplianceReportAction(formData: FormData) {
  const token = await getAccessToken();

  try {
    await opfinApi.createComplianceReport({
      report_type: value(formData, "report_type"),
      period_start: value(formData, "period_start"),
      period_end: value(formData, "period_end")
    }, token);
  } catch (error) {
    if (error instanceof OpfinApiError) {
      redirectWith("/admin/compliance", { error: error.kind, message: error.message });
    }

    redirectWith("/admin/compliance", { error: "server", message: "Compliance report creation failed" });
  }

  redirect("/admin/compliance?status=created");
}
