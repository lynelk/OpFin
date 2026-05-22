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

    return (
      <Screen title="KYC status" description="Identity verification status and customer-submitted KYC evidence.">
        <div className="grid grid-2">
          <section className="panel">
            <h2>Verification record</h2>
            {params?.status ? <StateNotice state="success" message="KYC case submitted for operations review." /> : null}
            {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}
            <table className="table">
              <tbody>
                <tr><th>Name</th><td>{user.name}</td></tr>
                <tr><th>Phone</th><td>{user.phone}</td></tr>
                <tr><th>NIN status</th><td><span className="badge ok">{user.nin_status ?? "Not available"}</span></td></tr>
                <tr><th>Latest case</th><td>{latestCase ? <span className="badge warn">{latestCase.status}</span> : "No KYC case submitted"}</td></tr>
                <tr><th>National ID</th><td>{maskSensitiveId(latestCase?.national_id ?? user.national_id)}</td></tr>
                <tr><th>Provider</th><td>{latestCase?.provider ?? "Not available"}</td></tr>
              </tbody>
            </table>
          </section>
          <section className="panel">
            <h2>Submit KYC evidence</h2>
            <form action={submitKycCaseAction} className="form-grid">
              <div className="field">
                <label htmlFor="national_id">National ID</label>
                <input id="national_id" name="national_id" defaultValue={user.national_id ?? ""} required />
              </div>
              <div className="field">
                <label htmlFor="document_type">Document type</label>
                <input id="document_type" name="document_type" defaultValue="national_id" required />
              </div>
              <button className="button" type="submit">Submit for review</button>
            </form>
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load KYC status.";

    return (
      <Screen title="KYC status" description="Identity status based on the current profile and NIN validation contract.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
