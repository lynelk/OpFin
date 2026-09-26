import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { WorkspaceContent } from "@/components/financial-intelligence/Workspace";
import { intelligenceApi, IntelligenceApiError } from "@/lib/api/financial-intelligence";
import { getAccessToken } from "@/lib/auth/session";
import { destination, errorMessages, label, positiveId, selectedTab, tabs, tabPermissions } from "@/lib/financial-intelligence/presentation";
import "./workspace.css";

export const dynamic = "force-dynamic";
export default async function FinancialIntelligencePage({ params, searchParams }: {
  params: Promise<{ id: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  try {
    const [{ id }, query, token] = await Promise.all([params, searchParams, getAccessToken()]);
    const space = positiveId(id);
    if (!token) throw new IntelligenceApiError(401, "Authentication required.");
    const context = await intelligenceApi.context(space, token);
    const tab = selectedTab(query.tab, context.permissions);
    const content = await WorkspaceContent({ context, query, token, tab });
    const error = typeof query.error === "string" ? errorMessages[query.error] : null;
    const notices: Record<string, string> = {
      saved: "The action has been recorded.", staged: "Source data passed the import checks and are staged for independent review. They are not yet a published management snapshot.",
      published: "The independent publication decision has been recorded.", rejected: "The import was rejected with its review evidence retained.",
      frozen: "A management report has been frozen from the published evidence.", uploaded: "The original statement was received. Check its processing and assurance status below.",
      revoked: "Analysis permission has been withdrawn. Retention obligations are separate."
    };
    const status = typeof query.status === "string" ? notices[query.status] : null;
    return <Screen title="Financial Intelligence" description={`${context.space_name} · Your authorised ${context.role} workspace.`}>
      <div className="fi-workspace"><p><Link href={`/spaces/${space}`}>Back to Financial Space</Link></p>
        <p className="fi-notice">Read-only intelligence alongside your core or MIS. Nothing here silently posts a payment, changes a contract, authenticates an uploaded document or grants access to another Space.</p>
        <nav className="fi-tabs" aria-label="Financial Intelligence sections">{tabs.filter(item => context.permissions.includes(tabPermissions[item])).map(item => <Link key={item} aria-current={item === tab ? "page" : undefined} href={destination(space, item)}>{item === "access" ? "Access controls" : label(item)}</Link>)}</nav>
        {error ? <p className="fi-notice" role="alert">{error}</p> : null}{status ? <p className="fi-notice" role="status">{status}</p> : null}
        {content}
      </div>
    </Screen>;
  } catch (error) {
    const kind = error instanceof IntelligenceApiError ? error.kind : "validation";
    return <Screen title="Financial Intelligence" description="Permissioned financial evidence and institutional decision support."><StateNotice state="server" message={errorMessages[kind] ?? errorMessages.server} /><p><Link href="/spaces">Return to your Financial Spaces</Link></p></Screen>;
  }
}
