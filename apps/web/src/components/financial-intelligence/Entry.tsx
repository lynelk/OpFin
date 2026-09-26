import Link from "next/link";
import { intelligenceApi } from "@/lib/api/financial-intelligence";
import { destination, selectedTab } from "@/lib/financial-intelligence/presentation";

/** Navigation follows server-granted access; a membership label alone never enables this capability. */
export async function FinancialIntelligenceEntry({ spaceId, token }: { spaceId: number; token?: string }) {
  if (!token) return null;
  try {
    const context = await intelligenceApi.context(spaceId, token);
    if (!context.permissions.length) return null;
    return <section className="panel"><p className="eyebrow">Financial evidence</p><h2>{context.space_type === "personal" ? "Statements & financial analysis" : "Financial Intelligence"}</h2>
      <p className="muted">{context.space_type === "personal" ? "Review permissioned statements and account movement without confusing financial consistency with issuer authenticity." : "Source-reconciled portfolio evidence, accountable action and institutional reports alongside your existing core or MIS."}</p>
      <Link className="button" href={destination(spaceId, selectedTab(undefined, context.permissions))}>Open Financial Intelligence</Link>
    </section>;
  } catch { return null; }
}
