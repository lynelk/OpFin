export type ApiErrorKind = "validation" | "unauthorized" | "forbidden" | "server" | "network";

export class OpfinApiError extends Error {
  constructor(
    public readonly kind: ApiErrorKind,
    message: string,
    public readonly status?: number,
    public readonly errors: Record<string, string[]> = {}
  ) {
    super(message);
    this.name = "OpfinApiError";
  }
}

export function classifyStatus(status: number): ApiErrorKind {
  if (status === 401) return "unauthorized";
  if (status === 403) return "forbidden";
  if (status === 422) return "validation";
  return "server";
}
