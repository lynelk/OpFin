import Link from "next/link";
import { Screen } from "@/components/Screen";
import { getAccessToken, getCurrentSession } from "@/lib/auth/session";
import { ShadowForm } from "./ShadowForm";

export default async function MerchantEvidencePage() {
  const [session, token] = await Promise.all([getCurrentSession(), getAccessToken()]);
  return (
    <Screen
      title="Optional merchant evidence"
      description="Inspect third-party commerce evidence without changing OpFin's financial-life mission, customer credit decisions or money records."
    >
      <section className="panel">
        <p className="eyebrow">EVIDENCE FIRST · NO LENDING ACTION</p>
        <h2>A connected capability, not a new OpFin identity</h2>
        <p>Stolets is an optional business-data provider. The borrower must have
          purpose-bound consent and a correctly linked Financial Space; the lender's
          current authority and a funded product must be verified independently
          before any live offer. All other OpFin features remain available without Stolets.</p>
        <p><Link href="/admin/growth-readiness">Review lender and capital configuration</Link></p>
      </section>
      {token && session.role === "platform_admin" ? <ShadowForm /> : (
        <section className="panel" role="status">
          An authorised platform administrator session is required to inspect private merchant evidence.
        </section>
      )}
    </Screen>
  );
}
