import { Screen } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";

export default async function KycPage() {
  const profile = await opfinApi.profile();
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
}
