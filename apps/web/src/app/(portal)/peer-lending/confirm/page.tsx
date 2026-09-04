import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { formatUgx } from "@/lib/format";
import { confirmPeerInvestmentAction, resendPeerOtpAction } from "@/app/focused-long-range-actions";

export default async function PeerInvestmentConfirmPage({
  searchParams
}: Readonly<{ searchParams?: Promise<{ intent?: string; amount?: string; status?: string; error?: string; message?: string }> }>) {
  const params = searchParams ? await searchParams : {};
  const intent = params.intent ?? "";
  const amount = Number(params.amount ?? 0);

  if (!intent) {
    return (
      <Screen title="Confirm investment" description="A valid marketplace investment is required.">
        <StateNotice state="validation" message="This confirmation link is missing the investment reference." />
        <Link className="button secondary" href="/peer-lending">Return to marketplace</Link>
      </Screen>
    );
  }

  return (
    <Screen title="Confirm investment" description="Verify this investment with the six-digit code sent to your registered phone.">
      {params.status === "otp-resent" ? <StateNotice state="success" message="A new one-time code was sent to your registered phone." /> : null}
      {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to confirm this investment."} /> : null}

      <section className="panel compass-next-action">
        <p className="eyebrow">FINAL CONFIRMATION</p>
        <h2>{amount > 0 ? formatUgx(amount) : "Marketplace investment"}</h2>
        <p className="muted">Check the amount, then enter your code. OpFin will send exactly this instruction to the governed payment rail.</p>
      </section>

      <section className="panel">
        <form action={confirmPeerInvestmentAction} className="form-grid">
          <input type="hidden" name="intent" value={intent} />
          <input type="hidden" name="amount" value={String(amount)} />
          <div className="field"><label htmlFor="otp">Six-digit code</label><input id="otp" name="otp" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" minLength={6} maxLength={6} required autoFocus /></div>
          <button className="button" type="submit">Confirm investment</button>
        </form>
        <form action={resendPeerOtpAction} className="form-grid">
          <input type="hidden" name="intent" value={intent} />
          <input type="hidden" name="amount" value={String(amount)} />
          <button className="button secondary" type="submit">Send a new code</button>
        </form>
      </section>

      <section className="panel">
        <h2>After confirmation</h2>
        <p className="muted">Payment processing may take time. OpFin shows the investment as settled only after provider evidence and reconciliation confirm that the money actually moved.</p>
      </section>
    </Screen>
  );
}
