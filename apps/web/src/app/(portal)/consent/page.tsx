import { createConsentAction, revokeConsentAction } from "@/app/actions";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

function purposeLabel(purpose: string): string {
  if (purpose === "credit_processing") return "Credit assessment";
  return purpose.replaceAll("_", " ").replace(/^./, (value) => value.toUpperCase());
}

export default async function ConsentPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const consentResponse = await opfinApi.consents(token);
    const consents = consentResponse.data.consents;
    const activeCreditConsent = consents.find((consent) => consent.purpose === "credit_processing" && consent.status === "granted");

    return (
      <Screen title="Data permissions" description="See what you have allowed OpFin to use, why it is needed and how to withdraw permission where permitted.">
        {params?.status ? <StateNotice state="success" message={params.status === "revoked" ? "Credit-assessment permission was withdrawn." : "Credit-assessment permission was recorded."} /> : null}
        {params?.message ? <StateNotice state={params.error === "forbidden" ? "forbidden" : "server"} message={params.message} /> : null}

        <section className="panel compass-next-action">
          <p className="eyebrow">CREDIT ASSESSMENT</p>
          <h2>{activeCreditConsent ? "Permission is active" : "Permission is not active"}</h2>
          <p className="muted">
            {activeCreditConsent
              ? "OpFin may use the information covered by the recorded policy version for credit assessment. Withdrawing permission does not erase records that must be retained by law or contract."
              : "OpFin will not use consent-dependent information for credit assessment until you explicitly grant permission."}
          </p>
          {activeCreditConsent ? (
            <form action={revokeConsentAction}>
              <input type="hidden" name="consent_id" value={activeCreditConsent.id} />
              <button className="button secondary compass-action" type="submit">Withdraw permission</button>
            </form>
          ) : (
            <form action={createConsentAction}>
              <button className="button compass-action" type="submit">Allow credit assessment</button>
            </form>
          )}
        </section>

        <section className="panel">
          <h2>Your permission history</h2>
          <p className="muted">Each record is versioned and audit logged so changes remain historically traceable.</p>
          <table className="table">
            <thead><tr><th>Purpose</th><th>Policy version</th><th>Status</th></tr></thead>
            <tbody>
              {consents.map((consent) => (
                <tr key={consent.id}>
                  <td>{purposeLabel(consent.purpose)}</td>
                  <td>{consent.policy_version}</td>
                  <td><span className={`badge ${consent.status === "granted" ? "ok" : "warn"}`}>{consent.status}</span></td>
                </tr>
              ))}
              {consents.length === 0 ? <tr><td colSpan={3}>No permission records are available yet.</td></tr> : null}
            </tbody>
          </table>
        </section>

        <section className="panel">
          <h2>What this control does not do</h2>
          <p className="muted">Granting one purpose does not grant every purpose. Employer, messaging, account-linking and other sensitive uses require their own approved legal basis and permission where applicable.</p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load your data permissions.";

    return (
      <Screen title="Data permissions" description="Review purpose-specific permissions from one place.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
