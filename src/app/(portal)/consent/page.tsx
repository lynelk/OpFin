import { cookies } from "next/headers";
import { createConsentAction, revokeConsentAction } from "@/app/actions";
import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";

export default async function ConsentPage({ searchParams }: { searchParams?: Promise<{ status?: string }> }) {
  const params = await searchParams;
  const cookieStore = await cookies();
  const consent = await opfinApi.consentState();
  const localStatus = cookieStore.get("opfin_demo_consent")?.value ?? consent.data.status;

  return (
    <Screen title="Consent management" description="Consent UI shell for CRB, mobile money, and employer-linked permissions.">
      <div className="grid grid-2">
        <section className="panel">
          <h2>Current consent state</h2>
          <span className="badge warn">{localStatus}</span>
          <p className="muted">Sandbox consent state only; the backend audit found no dedicated consent API yet.</p>
        </section>
        <section className="panel">
          <h2>Consent actions</h2>
          {params?.status ? <StateNotice state="success" message={`Sandbox consent ${params.status}.`} /> : null}
          <div className="auth-actions">
            <form action={createConsentAction}>
              <button className="button" type="submit">Create sandbox consent</button>
            </form>
            <form action={revokeConsentAction}>
              <button className="button secondary" type="submit">Revoke sandbox consent</button>
            </form>
          </div>
        </section>
      </div>
    </Screen>
  );
}
