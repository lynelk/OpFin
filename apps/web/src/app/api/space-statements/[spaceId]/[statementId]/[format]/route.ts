import { getAccessToken } from "@/lib/auth/session";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;

export async function GET(
  _request: Request,
  {
    params
  }: {
    params: Promise<{
      spaceId: string;
      statementId: string;
      format: string;
    }>;
  }
) {
  const { spaceId, statementId, format } = await params;
  if (!API_BASE_URL) {
    return new Response("OpFin API is not configured.", { status: 503 });
  }
  if (!["html", "csv"].includes(format)) {
    return new Response("Unsupported statement format.", { status: 404 });
  }

  const token = await getAccessToken();
  if (!token) {
    return new Response("Authentication required.", { status: 401 });
  }

  const upstream = await fetch(
    API_BASE_URL +
      "/financial-spaces/" +
      encodeURIComponent(spaceId) +
      "/statements/" +
      encodeURIComponent(statementId) +
      "/" +
      format,
    {
      headers: {
        Authorization: "Bearer " + token,
        Accept: format === "html" ? "text/html" : "text/csv"
      },
      cache: "no-store"
    }
  );

  const body = await upstream.arrayBuffer();
  return new Response(body, {
    status: upstream.status,
    headers: {
      "Content-Type":
        upstream.headers.get("content-type") ??
        (format === "html" ? "text/html; charset=UTF-8" : "text/csv; charset=UTF-8"),
      "Content-Disposition":
        upstream.headers.get("content-disposition") ??
        (format === "html" ? "inline" : "attachment"),
      "Cache-Control": "private, no-store",
      "X-Content-Type-Options": "nosniff"
    }
  });
}
