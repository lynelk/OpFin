import { mockApplications, mockConsentState, mockInstitutions, mockProducts, mockProfile, mockSchedule, mockTerms } from "../mock-data";
import { classifyStatus, OpfinApiError } from "./errors";
import type {
  ApiEnvelope,
  ConsentState,
  DemoDecision,
  DemoOffer,
  Institution,
  LoanApplication,
  LoginResponse,
  LoanProduct,
  ProductTerm,
  Profile,
  RepaymentScheduleRow
} from "../types";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;
const USE_MOCKS = process.env.NEXT_PUBLIC_USE_MOCK_API !== "false";

type RequestOptions = RequestInit & {
  token?: string;
  bodyJson?: unknown;
};

async function request<T>(path: string, init: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  if (!API_BASE_URL || USE_MOCKS) {
    return mockRequest<T>(path, init);
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

function mockRequest<T>(path: string, init: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  if (path === "/login") {
    return Promise.resolve(envelope({
      access_token: "sandbox-token",
      token_type: "Bearer",
      user: mockProfile.user
    } as T, "Sandbox login successful"));
  }
  if (path === "/profile") return Promise.resolve(envelope(mockProfile as T));
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
  profile: (token?: string) => request<Profile>("/profile", { token }),
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
  updateLoanApplicationStatus: (applicationId: number, status: string, token?: string) =>
    request<LoanApplication>(`/loan-applications/${applicationId}/status`, {
      method: "POST",
      bodyJson: { status },
      token
    }),
  consentState: (): Promise<ApiEnvelope<ConsentState>> => envelopeAsync(mockConsentState, "Sandbox consent state"),
  loanDecision: async (userId: number, token?: string): Promise<ApiEnvelope<DemoDecision>> => {
    const applications = await opfinApi.loanApplications(userId, token);
    const latest = applications.data[0];

    return envelopeAsync({
      status: latest?.status ?? "No application",
      message: latest ? `Latest backend application status: ${latest.status}` : "No application found.",
      source: latest ? "backend-application-status" : "sandbox"
    }, "Loan decision derived from documented application status");
  },
  loanOffer: (): Promise<ApiEnvelope<DemoOffer>> => envelopeAsync({
    status: "not-available",
    message: "Loan offer API is not documented yet; investor demo shows a sandbox placeholder."
  }, "Sandbox loan offer state")
};

function envelopeAsync<T>(data: T, message: string): Promise<ApiEnvelope<T>> {
  return Promise.resolve(envelope(data, message));
}
