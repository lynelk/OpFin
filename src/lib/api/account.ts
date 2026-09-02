import { classifyStatus, OpfinApiError } from "./errors";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;

export type AccountDeletionResult = {
  deletion_status: "completed" | "pending_obligations";
  message: string;
  case_number?: string;
  active_obligations?: string[];
  retained_record_categories?: string[];
};

export async function deleteAccount(password: string, token?: string): Promise<AccountDeletionResult> {
  if (!API_BASE_URL) throw new OpfinApiError("server", "OpFin API base URL is not configured.");

  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}/account`, {
      method: "DELETE",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {})
      },
      body: JSON.stringify({ password, confirmation: "DELETE" }),
      cache: "no-store"
    });
  } catch (error) {
    throw new OpfinApiError("network", error instanceof Error ? error.message : "OpFin API is unreachable");
  }

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new OpfinApiError(
      classifyStatus(response.status),
      typeof payload.message === "string" ? payload.message : `Account deletion failed: ${response.status}`,
      response.status,
      typeof payload.errors === "object" && payload.errors ? payload.errors : {}
    );
  }

  return payload.data as AccountDeletionResult;
}
