import Link from "next/link";
import { submitKycCaseAction } from "@/app/actions";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { maskSensitiveId } from "@/lib/format";

export default async function KycPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const [profile, kyc] = await Promise.all([
      opfinApi.profile(token),
      opfinApi.kycStatus(token)
    ]);
    const user = profile.data.user;
    const latestCase = kyc.data.latest_case;
    const isVerified = latestCase?.status === "verified" || user.nin_status === "verified";

    return (
      <Screen title="Identity verification" description="Verify your identity once, then reuse the confirmed record across eligible OpFin journeys instead of repeatedly supplying the same information.">
        {params?.status ? <StateNotice state="success" message="Your identity evidence was submitted for review." /> : null}
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}

        <section className="panel compass-next-action">
          <p className="eyebrow">VERIFICATION STATUS</p>
          <h2>{isVerified ? "Your identity is verified" : latestCase ? `Your verification is ${latestCase.status}` : "Verify your identity"}</h2>
          <p className="muted">
            {isVerified
              ? "OpFin will reuse this verified identity for journeys that require it. A product may still request additional information only when genuinely necessary."
              : "Identity verification protects your account and is required before regulated products can be activated. Your National ID is masked after submission."}
          </p>
          {isVerified ? <Link className="button compass-action" href="/dashboard">Return to Home</Link> : null}
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Your verification record</h2>
            <table className="table">
              <tbody>
                <tr><th>Name</th><td>{user.name}</td></tr>
                <tr><th>Phone</th><td>{user.phone}</td></tr>
                <tr><th>Identity status</th><td><span className={`badge ${isVerified ? "ok" : "warn"}`}>{isVerified ? "Verified" : latestCase?.status ?? user.nin_status ?? "Not verified"}</span></td></tr>
                <tr><th>National ID</th><td>{maskSensitiveId(latestCase?.national_id ?? user.national_id)}</td></tr>
              </tbody>
            </table>
          </section>

          <section className="panel">
            <h2>{isVerified ? "No action required" : latestCase ? "Update required evidence" : "Submit identity evidence"}</h2>
            {isVerified ? (
              <p className="muted">Do not resubmit identity documents unless OpFin specifically asks you to correct or refresh them.</p>
            ) : (
              <form action={submitKycCaseAction} className="form-grid">
                <div className="field">
                  <label htmlFor="national_id">National ID number</label>
                  <input id="national_id" name="national_id" defaultValue={user.national_id ?? ""} autoComplete="off" required />
                </div>
                <div className="field">
                  <label htmlFor="document_type">Document type</label>
                  <select id="document_type" name="document_type" defaultValue="national_id" required>
                    <option value="national_id">Uganda National ID</option>
                  </select>
                </div>
                <button className="button" type="submit">Submit identity evidence</button>
              </form>
            )}
          </section>
        </div>

        <section className="panel">
          <h2>Privacy by design</h2>
          <p className="muted">Products use the verified result and only the attributes they are authorised to access. OpFin does not require every journey to collect a fresh copy of your identity evidence.</p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load identity verification.";

    return (
      <Screen title="Identity verification" description="Review and complete identity verification from one place.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
