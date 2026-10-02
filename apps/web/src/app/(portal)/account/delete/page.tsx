import Link from "next/link";
import { deleteAccountAction, deleteOptionalDataAction } from "@/app/account-delete-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { getDeletionReadiness, type DeletionReadiness } from "@/lib/api/account";

export default async function AccountDeletePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string }> }>) {
  const params = searchParams ? await searchParams : {};
  const token = await getAccessToken();
  let readiness: DeletionReadiness | null = null;
  if (token) {
    try { readiness = await getDeletionReadiness(token); }
    catch { readiness = null; }
  }
  return (
    <Screen title="Account and optional-data deletion"
      description="Manage your account and optional data on the web without reinstalling the app."
      action={token ? <Link className="button secondary" href="/more">Back to More</Link> : <Link className="button secondary" href="/login?next=%2Faccount%2Fdelete">Verify account</Link>}>
      {params.status === "blocked" ? <StateNotice state="empty" message="Account deletion was rejected because obligations remain. No pending deletion request has been created. Resolve them, then submit a fresh request." /> : null}
      {params.status === "data-deleted" ? <StateNotice state="success" message="Your selected optional data was deleted. Your account remains active." /> : null}
      {params.error ? <StateNotice state="validation" message="The request was not completed. Check the confirmation and current PIN, refresh your account status, and try again." /> : null}
      <section className="panel">
        <h2>Your choice</h2>
        <p>Delete selected optional data while keeping the account, or request full account deletion. OpFin checks loans, financing arrangements, investments, savings, claims, payroll reservations and other unresolved financial activity before account closure.</p>
        <p>Any blocker causes immediate rejection. Once obligations are settled or closed, submit a fresh deletion request.</p>
      </section>
      {!token ? <section className="panel"><h2>Verify your account to continue</h2>
        <p>Sign in on this website to check obligations and securely manage deletion. The mobile app is not required.</p>
        <Link className="button" href="/login?next=%2Faccount%2Fdelete">Sign in</Link></section> : null}
      {token && !readiness ? <StateNotice state="server" message="Deletion readiness could not be confirmed. Account deletion is unavailable until the current status loads. Refresh this page; your account remains accessible." /> : null}
      {readiness && readiness.active_obligations.length > 0 ? <section className="panel" aria-label="Deletion blockers">
        <h2>Resolve these obligations first</h2>
        {readiness.active_obligations.map((item, index) => <article key={`${item.code}:${item.reference}:${index}`} className="panel">
          <h3>{item.label}</h3><p>Type: {item.code.replaceAll("_", " ")}. Reference: {item.reference}. Status: {item.status.replaceAll("_", " ")}.</p>
          {item.amount_minor !== null ? <p>Relevant amount: {item.currency} {item.amount_minor.toLocaleString("en-GB")}{item.amount_basis === "contract_total_not_current_balance" ? " (contract total, not a current balance)" : ""}</p> : null}
          {item.due_date ? <p>Due or relevant date: {item.due_date}</p> : null}
          <p>Provider: {item.provider.name}</p>
          {item.provider.phone || item.provider.email || item.provider.address
            ? <p>Recorded contact: {[item.provider.phone, item.provider.email, item.provider.address].filter(Boolean).join(" · ")}</p>
            : <p>No direct provider contact is recorded in OpFin. Preserve the reference above when seeking closure support.</p>}
        </article>)}
      </section> : null}
      <section className="panel"><h2>Records retained where required</h2>
        <p>Regulated financial, KYC/AML, accounting, settlement, credit-reporting, security, dispute and audit records are preserved where retention is legally required. Deleting optional data does not erase obligations or financial evidence.</p>
      </section>
      {readiness ? <section className="panel"><h2>Delete selected optional data</h2>
        <form action={deleteOptionalDataAction} className="form-grid">
          <fieldset><legend>Optional categories to delete</legend>{readiness.data_categories.map((category) => <label key={category.code} className="field">
            <span><input type="checkbox" name="data_categories" value={category.code} /> {category.label}</span><span>{category.description}</span>
          </label>)}</fieldset>
          <div className="field"><label htmlFor="data-pin">Current 6-digit PIN</label><input id="data-pin" name="pin" type="password" inputMode="numeric" autoComplete="current-password" minLength={6} maxLength={6} required /></div>
          <div className="field"><label htmlFor="data-confirmation">Type DELETE_DATA to confirm selected-data deletion</label><input id="data-confirmation" name="confirmation" pattern="DELETE_DATA" autoComplete="off" required /></div>
          <button className="button secondary" type="submit">Delete selected data and keep account</button>
        </form>
      </section> : null}
      {readiness ? <section className="panel"><h2>Delete the entire account</h2>
        <p>{readiness.can_delete_account ? "No current obligation prevents deletion. OpFin will check again when you submit." : "Full deletion is blocked until the obligations above are resolved."}</p>
        <form action={deleteAccountAction} className="form-grid">
          <div className="field"><label htmlFor="pin">Current 6-digit PIN</label><input id="pin" name="pin" type="password" inputMode="numeric" autoComplete="current-password" minLength={6} maxLength={6} required disabled={!readiness.can_delete_account} /></div>
          <div className="field"><label htmlFor="confirmation">Type DELETE to confirm full account deletion</label><input id="confirmation" name="confirmation" autoComplete="off" pattern="DELETE" required disabled={!readiness.can_delete_account} /></div>
          <button className="button" type="submit" disabled={!readiness.can_delete_account}>Delete my account</button>
        </form>
      </section> : null}
    </Screen>
  );
}
