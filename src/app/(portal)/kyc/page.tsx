import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function KycPage() {
  const token = await getAccessToken();

  try {
    const profile = await opfinApi.profile(token);
    const user = profile.data.user;

    return (
      <Screen title="KYC status" description="Identity status based on the current profile and NIN validation contract.">
        <section className="panel">
          <h2>Verification record</h2>
          <table className="table">
            <tbody>
              <tr><th>Name</th><td>{user.name}</td></tr>
              <tr><th>Phone</th><td>{user.phone}</td></tr>
              <tr><th>NIN status</th><td><span className="badge ok">{user.nin_status ?? "Not available"}</span></td></tr>
              <tr><th>National ID</th><td>{user.national_id ?? "Not available"}</td></tr>
              <tr><th>Date of birth</th><td>{user.date_of_birth ?? "Not available"}</td></tr>
            </tbody>
          </table>
        </section>
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
