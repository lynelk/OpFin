import { getAccessToken } from "@/lib/auth/session";

export async function GET(request: Request) {
  const token = await getAccessToken();
  if (!token) return new Response("Unauthorised", { status: 401 });
  const url = new URL(request.url);
  const id = Number(url.searchParams.get("run"));
  const view = url.searchParams.get("view");
  if (!Number.isSafeInteger(id) || id < 1 || !["loans", "portfolio"].includes(view ?? "")) {
    return new Response("Invalid ECL statement request.", { status: 400 });
  }
  const base = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!base) return new Response("OpFin API unavailable.", { status: 503 });
  const upstream = await fetch(`${base}/admin/institutional-ecl/runs/${id}/export/${view}`, {
    headers: { Accept: "text/csv", Authorization: `Bearer ${token}` }, cache: "no-store",
  });
  if (!upstream.ok) return new Response("Provision statement unavailable.", { status: upstream.status });
  return new Response(upstream.body, {
    status: 200, headers: {
      "Content-Type": "text/csv; charset=UTF-8",
      "Content-Disposition": upstream.headers.get("content-disposition") ?? "attachment; filename=opfin-ecl.csv",
      "Cache-Control": "private, no-store",
    },
  });
}
