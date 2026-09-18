import { NextRequest, NextResponse } from "next/server";
import { getAccessToken } from "@/lib/auth/session";

const API_BASE_URL = process.env.NEXT_PUBLIC_OPFIN_API_URL;

export async function GET(
  request: NextRequest,
  context: { params: Promise<{ report: string }> }
) {
  if (!API_BASE_URL) {
    return NextResponse.json(
      { success: false, message: "OpFin API base URL is not configured." },
      { status: 500 }
    );
  }

  const token = await getAccessToken();
  if (!token) {
    return NextResponse.json(
      { success: false, message: "Secure admin session is required." },
      { status: 401 }
    );
  }

  const { report } = await context.params;
  if (!/^\d+$/.test(report)) {
    return NextResponse.json(
      { success: false, message: "Invalid regulatory report reference." },
      { status: 400 }
    );
  }

  const format = request.nextUrl.searchParams.get("format") === "csv" ? "csv" : "json";
  const response = await fetch(
    `${API_BASE_URL}/admin/governance/regulatory-reports/${report}/export?format=${format}`,
    {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: format === "csv" ? "text/csv" : "application/json"
      },
      cache: "no-store"
    }
  );

  const body = await response.arrayBuffer();
  return new NextResponse(body, {
    status: response.status,
    headers: {
      "Content-Type":
        response.headers.get("Content-Type") ??
        (format === "csv" ? "text/csv; charset=UTF-8" : "application/json"),
      "Content-Disposition":
        response.headers.get("Content-Disposition") ??
        `attachment; filename="opfin-regulatory-report-${report}.${format}"`,
      ...(response.headers.get("X-OpFin-Evidence-Hash")
        ? { "X-OpFin-Evidence-Hash": response.headers.get("X-OpFin-Evidence-Hash")! }
        : {})
    }
  });
}
