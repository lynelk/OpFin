import { NextResponse } from "next/server";
import { getAccessToken, getCurrentSession } from "@/lib/auth/session";

export async function GET(
  _request: Request,
  context: { params: Promise<{ programme: string; format: string }> }
) {
  const { programme, format } = await context.params;
  const programmeId = Number(programme);

  if (!Number.isInteger(programmeId) || programmeId <= 0 || !["csv", "xlsx", "zip"].includes(format)) {
    return NextResponse.json({ message: "Invalid programme export request." }, { status: 400 });
  }

  const [token, session] = await Promise.all([getAccessToken(), getCurrentSession()]);
  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const baseUrl = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!baseUrl) {
    return NextResponse.json({ message: "OpFin API base URL is not configured." }, { status: 500 });
  }

  const path =
    session.role === "programme_partner"
      ? `/partner/inclusive-finance/programmes/${programmeId}/exports/${format}`
      : `/admin/inclusive-finance/programmes/${programmeId}/exports/${format}`;

  const response = await fetch(baseUrl + path, {
    headers: {
      Accept: "*/*",
      Authorization: `Bearer ${token}`
    },
    cache: "no-store"
  });

  if (!response.ok) {
    const payload = await response.json().catch(() => ({ message: "Programme export failed." }));
    return NextResponse.json(payload, { status: response.status });
  }

  const bytes = await response.arrayBuffer();
  return new Response(bytes, {
    status: 200,
    headers: {
      "Content-Type": response.headers.get("content-type") ?? "application/octet-stream",
      "Content-Disposition":
        response.headers.get("content-disposition") ??
        `attachment; filename="opfin-programme-${programmeId}.${format}"`,
      "Cache-Control": "private, no-store"
    }
  });
}
