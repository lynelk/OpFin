import {
  mockApplications,
  mockConsentRecord,
  mockConsentState,
  mockDemoConsent,
  mockDemoDecision,
  mockDemoOffer,
  mockInstitutions,
  mockInvestorSnapshot,
  mockKycCase,
  mockComplianceReports,
  mockLedgerTransactions,
  mockReconciliationItems,
  mockProducts,
  mockReconciliationRuns,
  mockProfile,
  mockSchedule,
  mockSupportCases,
  mockTerms
} from "../mock-data";
import type { CapabilityRegistry } from "../capabilities";
import { classifyStatus, OpfinApiError } from "./errors";
import type {
  ApiEnvelope,
  ComplianceReport,
  ComplianceExport,
  ConsentRecord,
  ConsentState,
  DemoApplicationResult,
  DemoConsent,
  DemoDashboard,
  DemoDecision,
  DemoOffer,
  DemoOfferAcceptance,
  Institution,
  InvestorDemoSnapshot,
  KycCase,
  LedgerTransaction,
  LoanApplication,
  LoginResponse,
  LoanProduct,
  ProductTerm,
  ReconciliationRun,
  ReconciliationItem,
  Profile,
  RepaymentScheduleRow,
  SupportCase
} from "../types";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;
const USE_MOCKS = process.env.NEXT_PUBLIC_USE_MOCK_API === "true";

type RequestOptions = RequestInit & {
  token?: string;
  bodyJson?: unknown;
};

async function request<T>(path: string, init: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  if (USE_MOCKS) {
    return mockRequest<T>(path, init);
  }

  if (!API_BASE_URL) {
    throw new OpfinApiError(
      "server",
      "OpFin API base URL is not configured. Set NEXT_PUBLIC_OPFIN_API_URL or explicitly enable mock mode."
    );
  }

  let response: Response;

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      body: init.bodyJson ? JSON.stringify(init.bodyJson) : init.body,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(init.token ? { Authorization: `Bearer ${init.token}` } : {}),
        ...init?.headers
      },
      cache: "no-store"
    });
  } catch (error) {
    throw new OpfinApiError(
      "network",
      error instanceof Error ? error.message : "OpFin API is unreachable"
    );
  }

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new OpfinApiError(
      classifyStatus(response.status),
      typeof payload.message === "string" ? payload.message : `OpFin API request failed: ${response.status}`,
      response.status,
      typeof payload.errors === "object" && payload.errors ? payload.errors : {}
    );
  }

  return payload as ApiEnvelope<T>;
}

function envelope<T>(data: T, message = "Mock data loaded"): ApiEnvelope<T> {
  return {
    success: true,
    message,
    data
  };
}

function sandboxSessionId(): string {
  return `sandbox-${globalThis.crypto.randomUUID()}`;
}

function mockCapabilityRegistry(): CapabilityRegistry {
  return {
    country: "UG",
    country_policy: {
      name: "Uganda",
      status: "PILOT",
      currency: "UGX",
      languages: ["en"],
      payment_platform: "cpay",
      payment_status: "SANDBOX"
    },
    capabilities: {
      home: { status: "AVAILABLE", owner: "opfin" },
      identity: { status: "AVAILABLE", owner: "opfin" },
      kyc: { status: "PILOT", owner: "opfin" },
      consent: { status: "AVAILABLE", owner: "opfin" },
      financial_passport: { status: "PILOT", owner: "opfin" },
      support: { status: "PILOT", owner: "opfin" },
      borrow: { status: "PILOT", owner: "opfin" },
      save: { status: "PLANNED", owner: "opfin" },
      insurance: { status: "PLANNED", owner: "opfin" },
      investments: { status: "PLANNED", owner: "opfin" },
      payments: { status: "SANDBOX", owner: "cpay" }
    }
  };
}

function mockRequest<T>(path: string, init: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  if (path === "/login") {
    return Promise.resolve(envelope({
      access_token: sandboxSessionId(),
      token_type: "Bearer",
      user: mockProfile.user
    } as T, "Sandbox login successful"));
  }
  if (path === "/profile") return Promise.resolve(envelope(mockProfile as T));
  if (path.startsWith("/capabilities")) return Promise.resolve(envelope(mockCapabilityRegistry() as T, "Sandbox capability registry loaded"));
  if (path === "/kyc/status") return Promise.resolve(envelope({ latest_case: mockKycCase } as T, "Production KYC sandbox state loaded"));
  if (path === "/kyc/cases" && init.method === "POST") {
    return Promise.resolve(envelope({ kyc_case: { ...mockKycCase, ...(init.bodyJson as object) } } as T, "Production KYC sandbox case submitted"));
  }
  if (path === "/consents" && init.method === "POST") {
    return Promise.resolve(envelope({ consent: { ...mockConsentRecord, ...(init.bodyJson as object), status: "granted" } } as T, "Production consent sandbox granted"));
  }
  if (path === "/consents") return Promise.resolve(envelope({ consents: [mockConsentRecord] } as T, "Production consent sandbox records loaded"));
  if (path.startsWith("/consents/") && init.method === "DELETE") {
    return Promise.resolve(envelope({ consent: { ...mockConsentRecord, status: "revoked", revoked_at: new Date().toISOString() } } as T, "Production consent sandbox revoked"));
  }
  if (path === "/support-cases" && init.method === "POST") {
    return Promise.resolve(envelope({ support_case: { ...mockSupportCases[0], ...(init.bodyJson as object), customer_id: mockProfile.user.id } } as T, "Sandbox customer support case created"));
  }
  if (path === "/support-cases") {
    return Promise.resolve(envelope({ support_cases: mockSupportCases.filter((supportCase) => supportCase.customer_id === mockProfile.user.id) } as T, "Sandbox customer support cases loaded"));
  }
  if (path === "/admin/reconciliation-runs" && init.method === "POST") {
    return Promise.resolve(envelope({ run: { ...mockReconciliationRuns[0], ...(init.bodyJson as object) }, item_count: 1 } as T, "Sandbox reconciliation run created"));
  }
  if (path.startsWith("/admin/reconciliation-runs/") && path.endsWith("/items")) {
    return Promise.resolve(envelope({ items: mockReconciliationItems } as T, "Sandbox reconciliation items loaded"));
  }
  if (path.startsWith("/admin/reconciliation-items/") && init.method === "PATCH") {
    return Promise.resolve(envelope({ item: { ...mockReconciliationItems[0], status: "matched", ...(init.bodyJson as object), resolved_at: new Date().toISOString() } } as T, "Sandbox reconciliation item resolved"));
  }
  if (path === "/admin/reconciliation-runs") return Promise.resolve(envelope({ runs: mockReconciliationRuns } as T, "Sandbox reconciliation runs loaded"));
  if (path === "/admin/support-cases" && init.method === "POST") {
    return Promise.resolve(envelope({ support_case: { ...mockSupportCases[0], ...(init.bodyJson as object) } } as T, "Sandbox support case created"));
  }
  if (path.startsWith("/admin/support-cases/") && init.method === "PATCH") {
    return Promise.resolve(envelope({ support_case: { ...mockSupportCases[0], status: "resolved", ...(init.bodyJson as object), notes: [{ id: 1, note: "Sandbox note", is_internal: true }] } } as T, "Sandbox support case updated"));
  }
  if (path === "/admin/support-cases") return Promise.resolve(envelope({ support_cases: mockSupportCases } as T, "Sandbox support cases loaded"));
  if (path === "/admin/compliance-reports" && init.method === "POST") {
    return Promise.resolve(envelope({ report: { ...mockComplianceReports[0], ...(init.bodyJson as object) } } as T, "Sandbox compliance report created"));
  }
  if (path.startsWith("/admin/compliance-reports/") && path.endsWith("/exports")) {
    return Promise.resolve(envelope({ export: { id: 1, compliance_report_id: mockComplianceReports[0].id, format: "csv", status: "generated", manifest: {}, generated_at: new Date().toISOString() } } as T, "Sandbox compliance export created"));
  }
  if (path === "/admin/compliance-reports") return Promise.resolve(envelope({ reports: mockComplianceReports } as T, "Sandbox compliance reports loaded"));
  if (path === "/admin/ledger-transactions") return Promise.resolve(envelope({ ledger_transactions: mockLedgerTransactions } as T, "Sandbox ledger transactions loaded"));
  if (path === "/demo/dashboard") {
    return Promise.resolve(envelope({
      mock_integrations: ["affordability", "decisioning", "mobile_money_disbursement"],
      profile: mockProfile.user,
      kyc: {
        status: mockProfile.user.nin_status,
        national_id: mockProfile.user.national_id,
        date_of_birth: mockProfile.user.date_of_birth,
        mock_integration: false
      },
      consent: mockDemoConsent,
      latest_application: {
        ...mockApplications[0],
        demo_decision: mockDemoDecision,
        demo_offer: mockDemoOffer
      }
    } as T, "Sandbox investor demo dashboard"));
  }
  if (path === "/demo/consent" && init.method === "POST") return Promise.resolve(envelope({
    mock_integration: true,
    status: "granted",
    consent: mockDemoConsent
  } as T, "Sandbox investor-demo consent granted"));
  if (path === "/demo/consent" && init.method === "DELETE") return Promise.resolve(envelope({
    mock_integration: true,
    status: "revoked",
    consent: { ...mockDemoConsent, status: "revoked", revoked_at: new Date().toISOString() }
  } as T, "Sandbox investor-demo consent revoked"));
  if (path === "/demo/loan-applications" && init.method === "POST") {
    return Promise.resolve(envelope({
      application: {
        ...mockApplications[0],
        amount: typeof init.bodyJson === "object" && init.bodyJson && "amount" in init.bodyJson ? String(init.bodyJson.amount) : "250000",
        status: "Approved"
      },
      decision: mockDemoDecision,
      offer: mockDemoOffer
    } as T, "Sandbox application decision completed"));
  }
  if (path.startsWith("/demo/loan-applications/") && path.endsWith("/decision")) {
    return Promise.resolve(envelope({ mock_integration: true, decision: mockDemoDecision } as T, "Sandbox decision loaded"));
  }
  if (path.startsWith("/demo/loan-applications/") && path.endsWith("/offer")) {
    return Promise.resolve(envelope({ mock_integration: true, offer: mockDemoOffer } as T, "Sandbox offer loaded"));
  }
  if (path.startsWith("/demo/loan-offers/") && path.endsWith("/accept")) {
    return Promise.resolve(envelope({
      offer: { ...mockDemoOffer, status: "accepted", accepted_at: new Date().toISOString() },
      loan: mockApplications[1].loan,
      mobile_money: mockInvestorSnapshot.mobile_money[0],
      ledger_entries: mockInvestorSnapshot.ledger_entries,
      repayment_schedule: mockSchedule
    } as T, "Sandbox offer accepted"));
  }
  if (path === "/demo/admin/investor-snapshot") return Promise.resolve(envelope(mockInvestorSnapshot as T, "Sandbox admin snapshot loaded"));
  if (path === "/products") return Promise.resolve(envelope(mockProducts as T));
  if (path === "/institutions") return Promise.resolve(envelope(mockInstitutions as T));
  if (path.startsWith("/product-terms/")) return Promise.resolve(envelope(mockTerms as T));
  if (path.startsWith("/loan-applications/") && path.endsWith("/status")) {
    return Promise.resolve(envelope({
      ...mockApplications[0],
      status: typeof init.bodyJson === "object" && init.bodyJson && "status" in init.bodyJson ? String(init.bodyJson.status) : "Approved"
    } as T, "Sandbox review status updated"));
  }
  if (path.startsWith("/loan-applications/")) return Promise.resolve(envelope(mockApplications as T));
  if (path === "/loan-applications" && init.method === "POST") {
    return Promise.resolve(envelope({
      ...mockApplications[0],
      amount: typeof init.bodyJson === "object" && init.bodyJson && "amount" in init.bodyJson ? String(init.bodyJson.amount) : "100000",
      status: "Pending"
    } as T, "Sandbox loan application submitted"));
  }
  if (path.startsWith("/loan-balance/")) return Promise.resolve(envelope({ outstandingAmount: 275000 } as T));
  if (path.startsWith("/loans/") && path.endsWith("/schedule")) return Promise.resolve(envelope(mockSchedule as T));

  return Promise.resolve(envelope({} as T, "No mock contract is defined for this endpoint"));
}

export const opfinApi = {
  login: (phone: string, password: string) =>
    request<LoginResponse>("/login", {
      method: "POST",
      bodyJson: { phone, password }
    }),
  logout: (token?: string) => request<Record<string, never>>("/logout", { method: "POST", token }),
  profile: (token?: string) => request<Profile>("/profile", { token }),
  capabilities: (country = "UG", token?: string) =>
    request<CapabilityRegistry>(`/capabilities?country=${encodeURIComponent(country)}`, { token }),
  kycStatus: (token?: string) => request<{ latest_case: KycCase | null }>("/kyc/status", { token }),
  submitKycCase: (payload: { national_id: string; provider?: string; provider_reference?: string; evidence?: Record<string, unknown> }, token?: string) =>
    request<{ kyc_case: KycCase }>("/kyc/cases", { method: "POST", bodyJson: payload, token }),
  consents: (token?: string) => request<{ consents: ConsentRecord[] }>("/consents", { token }),
  grantConsent: (payload: { purpose: string; policy_version: string; channel?: string; metadata?: Record<string, unknown> }, token?: string) =>
    request<{ consent: ConsentRecord }>("/consents", { method: "POST", bodyJson: payload, token }),
  revokeConsent: (consentId: number, token?: string) =>
    request<{ consent: ConsentRecord }>(`/consents/${consentId}`, { method: "DELETE", token }),
  customerSupportCases: (token?: string) => request<{ support_cases: SupportCase[] }>("/support-cases", { token }),
  createCustomerSupportCase: (
    payload: { category: string; subject: string; description: string; related_reference?: string; related_type?: string },
    token?: string
  ) => request<{ support_case: SupportCase }>("/support-cases", { method: "POST", bodyJson: payload, token }),
  reconciliationRuns: (token?: string) => request<{ runs: ReconciliationRun[] }>("/admin/reconciliation-runs", { token }),
  createReconciliationRun: (payload: { provider: string; business_date: string }, token?: string) =>
    request<{ run: ReconciliationRun; item_count: number }>("/admin/reconciliation-runs", { method: "POST", bodyJson: payload, token }),
  reconciliationItems: (runId: number, token?: string) =>
    request<{ items: ReconciliationItem[] }>(`/admin/reconciliation-runs/${runId}/items`, { token }),
  resolveReconciliationItem: (itemId: number, payload: { status: string; provider_amount_minor?: number; notes: string }, token?: string) =>
    request<{ item: ReconciliationItem }>(`/admin/reconciliation-items/${itemId}`, { method: "PATCH", bodyJson: payload, token }),
  supportCases: (token?: string) => request<{ support_cases: SupportCase[] }>("/admin/support-cases", { token }),
  createSupportCase: (
    payload: { customer_id: number; category: string; priority?: string; subject: string; description: string; assigned_to?: number },
    token?: string
  ) => request<{ support_case: SupportCase }>("/admin/support-cases", { method: "POST", bodyJson: payload, token }),
  updateSupportCase: (caseId: number, payload: { status: string; assigned_to?: number; priority?: string; note?: string }, token?: string) =>
    request<{ support_case: SupportCase }>(`/admin/support-cases/${caseId}`, { method: "PATCH", bodyJson: payload, token }),
  complianceReports: (token?: string) => request<{ reports: ComplianceReport[] }>("/admin/compliance-reports", { token }),
  createComplianceReport: (payload: { report_type: string; period_start: string; period_end: string; parameters?: Record<string, unknown> }, token?: string) =>
    request<{ report: ComplianceReport }>("/admin/compliance-reports", { method: "POST", bodyJson: payload, token }),
  createComplianceExport: (reportId: number, payload: { format: string }, token?: string) =>
    request<{ export: ComplianceExport }>(`/admin/compliance-reports/${reportId}/exports`, { method: "POST", bodyJson: payload, token }),
  ledgerTransactions: (token?: string) => request<{ ledger_transactions: LedgerTransaction[] }>("/admin/ledger-transactions", { token }),
  products: (token?: string) => request<LoanProduct[]>("/products", { token }),
  institutions: (token?: string) => request<Institution[]>("/institutions", { token }),
  productTerms: (productId: number, token?: string) => request<ProductTerm[]>(`/product-terms/${productId}`, { token }),
  loanApplications: (userId: number, token?: string) => request<LoanApplication[]>(`/loan-applications/${userId}`, { token }),
  loanBalance: (userId: number, token?: string) => request<{ outstandingAmount: number }>(`/loan-balance/${userId}`, { token }),
  submitLoanApplication: (
    payload: {
      loan_product_id: number;
      loan_product_term_id: number;
      institution_id: number;
      amount: number;
      reason: string;
    },
    token?: string
  ) => request<LoanApplication>("/loan-applications", { method: "POST", bodyJson: payload, token }),
  repaymentSchedule: (): Promise<ApiEnvelope<RepaymentScheduleRow[]>> =>
    envelopeAsync(mockSchedule, "Sandbox repayment schedule; no documented backend schedule endpoint exists yet"),
  demoDashboard: (token?: string) => request<DemoDashboard>("/demo/dashboard", { token }),
  grantDemoConsent: (token?: string) =>
    request<{ mock_integration: true; status: string; consent: DemoConsent }>("/demo/consent", {
      method: "POST",
      bodyJson: { purpose: "credit_processing" },
      token
    }),
  revokeDemoConsent: (token?: string) =>
    request<{ mock_integration: true; status: string; consent: DemoConsent }>("/demo/consent", {
      method: "DELETE",
      token
    }),
  submitDemoLoanApplication: (
    payload: {
      loan_product_id: number;
      loan_product_term_id: number;
      institution_id: number;
      amount: number;
      reason: string;
    },
    token?: string
  ) => request<DemoApplicationResult>("/demo/loan-applications", { method: "POST", bodyJson: payload, token }),
  demoDecision: (applicationId: number, token?: string) =>
    request<{ mock_integration: true; decision: DemoDecision | null }>(`/demo/loan-applications/${applicationId}/decision`, { token }),
  demoOffer: (applicationId: number, token?: string) =>
    request<{ mock_integration: true; offer: DemoOffer | null }>(`/demo/loan-applications/${applicationId}/offer`, { token }),
  acceptDemoOffer: (offerId: number, token?: string) =>
    request<DemoOfferAcceptance>(`/demo/loan-offers/${offerId}/accept`, { method: "POST", token }),
  investorDemoAdminSnapshot: (token?: string) =>
    request<InvestorDemoSnapshot>("/demo/admin/investor-snapshot", { token }),
  updateLoanApplicationStatus: (applicationId: number, status: string, token?: string) =>
    request<LoanApplication>(`/loan-applications/${applicationId}/status`, {
      method: "POST",
      bodyJson: { status },
      token
    }),
  consentState: (): Promise<ApiEnvelope<ConsentState>> => envelopeAsync(mockConsentState, "Sandbox consent state"),
  loanDecision: async (_userId: number, _token?: string): Promise<ApiEnvelope<DemoDecision>> =>
    envelopeAsync(mockDemoDecision, "Sandbox decision retained for legacy screen compatibility"),
  loanOffer: (): Promise<ApiEnvelope<DemoOffer>> =>
    envelopeAsync(mockDemoOffer, "Sandbox offer retained for legacy screen compatibility")
};

function envelopeAsync<T>(data: T, message: string): Promise<ApiEnvelope<T>> {
  return Promise.resolve(envelope(data, message));
}
