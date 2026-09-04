import Link from "next/link";
import { submitSimpleLoanApplicationAction } from "@/app/simple-credit-actions";
import { Screen, StateNotice } from "@/components/Screen";

export default async function LoanApplyPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string }> }) {
  const params = await searchParams;

  return (
    <Screen
      title="Tell us what you need"
      description="One short request is enough. OpFin checks the active credit routes you may use; you do not need to choose a lender, product code or internal term configuration."
    >
      <section className="panel compass-next-action">
        <p className="eyebrow">ONE REQUEST</p>
        <h2>Amount and purpose. OpFin handles the routing.</h2>
        <p className="muted">Submitting starts assessment only. It never approves a loan or triggers a payout by itself.</p>
      </section>

      <section className="panel">
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}
        <form action={submitSimpleLoanApplicationAction} className="form-grid">
          <div className="field">
            <label htmlFor="amount">How much do you need? (UGX)</label>
            <input id="amount" name="amount" type="number" inputMode="numeric" min="1" placeholder="100000" required autoFocus />
          </div>
          <div className="field">
            <label htmlFor="reason">What do you need it for?</label>
            <textarea id="reason" name="reason" rows={4} placeholder="For example: school fees, emergency, business stock" required />
          </div>
          <div className="placeholder">
            After assessment, you will see the responsible provider, amount received, every fee, interest, total repayment, repayment dates and offer expiry before you can accept anything.
          </div>
          <button className="button" type="submit">Check my options</button>
        </form>
      </section>

      <section className="panel">
        <h2>Prefer marketplace funding?</h2>
        <p className="muted">You can also ask verified investors to fund an independently reviewed request through the OpFin Marketplace.</p>
        <Link className="button secondary" href="/peer-lending/borrow">Borrow from investors</Link>
      </section>
    </Screen>
  );
}
