import { mockApplications } from "../mock-data";
import type { ApiEnvelope, LoanApplication } from "../types";
import { classifyStatus, OpfinApiError } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;
const USE_MOCKS = process.env.NEXT_PUBLIC_USE_MOCK_API === "true";

export type SimpleCreditApplicationPayload = {
  amount_minor: number;
  reason: string;
};

export async function submitSimpleCreditApplication(
  payload: SimpleCreditApplicationPayload,
  token?: string
): Promise<ApiEnvelope<{ application: LoanApplication; next_state: string; routing_mode?: string }>> {
  if (USE_MOCKS) {
    return {
      success: true,
      message: "Sandbox credit application submitted for assessment.",
      data: {
        application: {
          ...mockApplications[0],
          amount: String(payload.amount_minor),
          reason: payload.reason,
          status: "Pending"
        },
        next_state: "assessment",
        routing_mode: "system_selected"
      }
    };
  }

  if (!API_BASE_URL) {
    throw new OpfinApiError("server", "OpFin API base URL is not configured.");
  }

  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}/credit/applications`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {})
      },
      body: JSON.stringify(payload),
      cache: "no-store"
    });
  } catch (error) {
    throw new OpfinApiError("network", error instanceof Error ? error.message : "OpFin API is unreachable");
  }

  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new OpfinApiError(
      classifyStatus(response.status),
      typeof body.message === "string" ? body.message : `OpFin API request failed: ${response.status}`,
      response.status,
      typeof body.errors === "object" && body.errors ? body.errors : {}
    );
  }

  return body as ApiEnvelope<{ application: LoanApplication; next_state: string; routing_mode?: string }>;
}
