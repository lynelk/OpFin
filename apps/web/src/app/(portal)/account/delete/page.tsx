import Link from "next/link";
import { deleteAccountAction } from "@/app/account-delete-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";

export default async function AccountDeletePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; case?: string; error?: string; message?: string }> }>) {
  const params = searchParams ? await searchParams : {};
  const token = await getAccessToken();
  const authenticated = Boolean(token);

  return (
    <Screen
      title="Delete your OpFin account"
      description="This is OpFin's official account-deletion resource. You can start and complete the request on the web without reinstalling the Android app."
      action={authenticated ? <Link className="button secondary" href="/more">Back to More</Link> : <Link className="button secondary" href="/login?next=%2Faccount%2Fdelete">Verify account</Link>}
    >
      {params.status === "pending" ? (
        <StateNotice state="empty" message={`${params.message ?? "Your deletion request is recorded."}${params.case ? ` Case ${params.case}.` : ""}`} />
      ) : null}
      {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to delete your account."} /> : null}

      <section className="panel compass-next-action">
        <p className="eyebrow">ACCOUNT CONTROL</p>
        <h2>Request deletion of your OpFin account and associated data.</h2>
        <p className="muted">If you have no active regulated or financial obligations, deletion is completed immediately. If an active loan, savings, protection or peer-lending relationship must first be closed, OpFin records this deletion request and completes it through the regulated closure process.</p>
      </section>

      {!authenticated ? (
        <section className="panel">
          <h2>Verify your account to continue</h2>
          <p className="muted">For security, OpFin asks you to sign in on this website before a deletion request can be submitted. This verifies that the request belongs to the account holder. You do not need the Android app installed.</p>
          <Link className="button" href="/login?next=%2Faccount%2Fdelete">Sign in to request deletion</Link>
        </section>
      ) : null}

      <section className="panel">
        <h2>What is removed</h2>
        <ul>
          <li>Your active login and session access.</li>
          <li>Direct profile identifiers that are not required to be retained.</li>
          <li>Optional household, budgeting, connected-account and other customer-entered context that can lawfully be deleted.</li>
          <li>Active purpose-specific consents are revoked when deletion completes.</li>
        </ul>
      </section>

      <section className="panel">
        <h2>What may be retained</h2>
        <p className="muted">Financial transaction, loan, repayment, KYC/AML, credit-reporting, accounting, reconciliation, dispute, fraud-prevention and audit evidence may be retained only where applicable law, regulation or another legitimate legal obligation requires it, and only for the required retention period. Retention does not keep the deleted account active or usable.</p>
      </section>

      {authenticated ? (
        <section className="panel">
          <h2>Confirm deletion</h2>
          <form action={deleteAccountAction} className="form-grid">
            <div className="field">
              <label htmlFor="pin">Current 6-digit PIN</label>
              <input id="pin" name="pin" type="password" inputMode="numeric" autoComplete="current-password" minLength={6} maxLength={6} required />
            </div>
            <div className="field">
              <label htmlFor="confirmation">Type DELETE to confirm</label>
              <input id="confirmation" name="confirmation" autoComplete="off" pattern="DELETE" required />
            </div>
            <button className="button" type="submit">Delete my account</button>
          </form>
        </section>
      ) : null}
    </Screen>
  );
}
