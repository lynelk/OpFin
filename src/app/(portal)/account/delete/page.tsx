import Link from "next/link";
import { deleteAccountAction } from "@/app/account-delete-actions";
import { Screen, StateNotice } from "@/components/Screen";

export default async function AccountDeletePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; case?: string; error?: string; message?: string }> }>) {
  const params = searchParams ? await searchParams : {};

  return (
    <Screen title="Delete account" description="Close your OpFin account without hiding what must still be retained or serviced under financial and regulatory obligations." action={<Link className="button secondary" href="/more">Back to More</Link>}>
      {params.status === "pending" ? (
        <StateNotice state="empty" message={`${params.message ?? "Your deletion request is recorded."}${params.case ? ` Case ${params.case}.` : ""}`} />
      ) : null}
      {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to delete your account."} /> : null}

      <section className="panel compass-next-action">
        <p className="eyebrow">ACCOUNT CONTROL</p>
        <h2>You can delete your OpFin account from here.</h2>
        <p className="muted">If you have no active regulated or financial obligations, deletion is completed immediately. If an active loan, savings, protection or peer-lending relationship must first be closed, OpFin records this deletion request and completes it through the regulated closure process.</p>
      </section>

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
        <p className="muted">Financial transaction, loan, repayment, KYC/AML, credit-reporting, accounting, reconciliation, dispute, fraud-prevention and audit evidence may be retained only where applicable law, regulation or a legitimate legal obligation requires it. Retention does not keep the deleted account active.</p>
      </section>

      <section className="panel">
        <h2>Confirm deletion</h2>
        <form action={deleteAccountAction} className="form-grid">
          <div className="field">
            <label htmlFor="password">Current password</label>
            <input id="password" name="password" type="password" autoComplete="current-password" required />
          </div>
          <div className="field">
            <label htmlFor="confirmation">Type DELETE to confirm</label>
            <input id="confirmation" name="confirmation" autoComplete="off" pattern="DELETE" required />
          </div>
          <button className="button" type="submit">Delete my account</button>
        </form>
      </section>
    </Screen>
  );
}
