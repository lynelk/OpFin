import type { ReactNode } from "react";
import { FinancialIntelligenceEntry } from "@/components/financial-intelligence/Entry";
import { getAccessToken } from "@/lib/auth/session";
import { positiveId } from "@/lib/financial-intelligence/presentation";

export default async function FinancialSpaceLayout({ children, params }: {
  children: ReactNode;
  params: Promise<{ id: string }>;
}) {
  if (process.env.OPFIN_FINANCIAL_INTELLIGENCE_ENABLED !== "true") return children;
  const { id } = await params;
  let spaceId: number;
  try { spaceId = positiveId(id); } catch { return children; }
  const token = await getAccessToken();
  return <>{children}<FinancialIntelligenceEntry spaceId={spaceId} token={token} /></>;
}
