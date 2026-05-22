import { createConsentAction, revokeConsentAction } from "@/app/actions";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function ConsentPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const consentResponse = await opfinApi.consents(token);
    const consents = consentResponse.data.consents;
    const activeCreditConsent = consents.find((consent) => consent.purpose === "credit_processing" && consent.status === "granted");

    return (
      <Screen title="Consent management" description="Production consent records for credit processing and related permissions.">
        <div className="grid grid-2">
          <section className="panel">
            <h2>Current consent state</h2>
            <span className="badge warn">{activeCreditConsent?.status ?? "not granted"}</span>
            <p className="muted">Credit-processing consent is versioned and audit logged.</p>
            <table className="table">
              <tbody>
                {consents.map((consent) => (
                  <tr key={consent.id}>
                    <th>{consent.purpose}</th>
                    <td>{consent.policy_version}</td>
                    <td>{consent.status}</td>
                  </tr>
                ))}
                {consents.length === 0 ? <tr><td>No consent records are available.</td></tr> : null}
              </tbody>
            </table>
          </section>
          <section className="panel">
            <h2>Consent actions</h2>
            {params?.status ? <StateNotice state="success" message={`Consent ${params.status}.`} /> : null}
            {params?.message ? <StateNotice state={params.error === "forbidden" ? "forbidden" : "server"} message={params.message} /> : null}
            <div className="auth-actions">
              <form action={createConsentAction}>
                <button className="button" type="submit">Grant credit consent</button>
              </form>
              {activeCreditConsent ? (
                <form action={revokeConsentAction}>
                  <input type="hidden" name="consent_id" value={activeCreditConsent.id} />
                  <button className="button secondary" type="submit">Revoke credit consent</button>
                </form>
              ) : null}
            </div>
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load consent state.";

    return (
      <Screen title="Consent management" description="Consent UI shell for CRB, mobile money, and employer-linked permissions.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
