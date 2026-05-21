import { mockApplications, mockProducts, mockProfile, mockSchedule, mockTerms } from "../mock-data";
import type { ApiEnvelope, LoanApplication, LoanProduct, ProductTerm, Profile, RepaymentScheduleRow } from "../types";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;
const USE_MOCKS = process.env.NEXT_PUBLIC_USE_MOCK_API !== "false";

async function request<T>(path: string, init?: RequestInit): Promise<ApiEnvelope<T>> {
  if (!API_BASE_URL || USE_MOCKS) {
    return mockRequest<T>(path);
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...init?.headers
    },
    cache: "no-store"
  });

  if (!response.ok) {
    throw new Error(`OpFin API request failed: ${response.status}`);
  }

  return response.json() as Promise<ApiEnvelope<T>>;
}

function envelope<T>(data: T, message = "Mock data loaded"): ApiEnvelope<T> {
  return {
    success: true,
    message,
    data
  };
}

function mockRequest<T>(path: string): Promise<ApiEnvelope<T>> {
  if (path === "/profile") return Promise.resolve(envelope(mockProfile as T));
  if (path === "/products") return Promise.resolve(envelope(mockProducts as T));
  if (path.startsWith("/product-terms/")) return Promise.resolve(envelope(mockTerms as T));
  if (path.startsWith("/loan-applications/")) return Promise.resolve(envelope(mockApplications as T));
  if (path.startsWith("/loan-balance/")) return Promise.resolve(envelope({ outstandingAmount: 275000 } as T));
  if (path.startsWith("/loans/") && path.endsWith("/schedule")) return Promise.resolve(envelope(mockSchedule as T));

  return Promise.resolve(envelope({} as T, "No mock contract is defined for this endpoint"));
}

export const opfinApi = {
  profile: () => request<Profile>("/profile"),
  products: () => request<LoanProduct[]>("/products"),
  productTerms: (productId: number) => request<ProductTerm[]>(`/product-terms/${productId}`),
  loanApplications: (userId: number) => request<LoanApplication[]>(`/loan-applications/${userId}`),
  loanBalance: (userId: number) => request<{ outstandingAmount: number }>(`/loan-balance/${userId}`),
  repaymentSchedule: (loanId: number) => request<RepaymentScheduleRow[]>(`/loans/${loanId}/schedule`)
};
