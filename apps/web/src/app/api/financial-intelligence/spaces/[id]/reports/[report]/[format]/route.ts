import { reportBytes } from "@/lib/api/financial-intelligence";
import { getAccessToken } from "@/lib/auth/session";
import { positiveId } from "@/lib/financial-intelligence/presentation";

export async function GET(_request: Request, { params }: { params: Promise<{ id: string; report: string; format: string }> }) {
  const values = await params;
  if (values.format !== "csv" && values.format !== "html") return new Response("Unsupported report format.", { status: 404 });
  const token = await getAccessToken();
  if (!token) return new Response("Sign in to view this report.", { status: 401, headers: { "Cache-Control": "no-store" } });
  try {
    const space = positiveId(values.id);
    const report = positiveId(values.report);
    const upstream = await reportBytes(space, report, values.format, token);
    if (!upstream.ok) return new Response("This report is not available to this Financial Space and user.", { status: upstream.status, headers: { "Cache-Control": "no-store" } });
    return new Response(upstream.body, { status: 200, headers: {
      "Content-Type": values.format === "csv" ? "text/csv; charset=utf-8" : "text/html; charset=utf-8",
      "Content-Disposition": `${values.format === "csv" ? "attachment" : "inline"}; filename="opfin-intelligence-${report}.${values.format}"`,
      "Cache-Control": "private, no-store, max-age=0", "X-Content-Type-Options": "nosniff", "Referrer-Policy": "no-referrer",
      "Content-Security-Policy": "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
    } });
  } catch { return new Response("The report could not be retrieved.", { status: 503, headers: { "Cache-Control": "no-store" } }); }
}
