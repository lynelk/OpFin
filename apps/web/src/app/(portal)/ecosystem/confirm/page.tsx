import Link from "next/link";
import { confirmLongRangeFinancialAction, resendLongRangeOtpAction } from "@/app/long-range-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { formatUgx } from "@/lib/format";

function titleForPurpose(purpose: string): string {
  if (purpose === "participatory") return "Confirm participatory funding";
  if (purpose === "asset-deposit") return "Confirm asset-finance deposit";
  return "Confirm financial instruction";
}

export default async function FinancialStepUpPage({
  searchParams
}: Readonly<{
  searchParams?: Promise<{ intent?: string; purpose?: string; amount?: string; status?: string; error?: string; message?: string }>;
}>) {
  const params = searchParams ? await searchParams : {};
  const intent = params.intent ?? "";
  const purpose = params.purpose ?? "financial-action";
  const amount = Number(params.amount ?? 0);

  if (!intent) {
    return (
      <Screen title="Confirm financial instruction" description="A valid financial instruction is required before step-up authentication.">
        <StateNotice state="validation" message="This confirmation link does not contain a valid financial instruction reference." />
        <Link className="button secondary" href="/ecosystem">Return to connected financial life</Link>
      </Screen>
    );
  }

  return (
    <Screen
      title={titleForPurpose(purpose)}
      description="A fresh one-time code is required before OpFin can submit this instruction to the governed CPay rail. Provider acceptance is not treated as settlement."
    >
      {params.status === "otp-resent" ? <StateNotice state="success" message="A new one-time code was sent to your registered phone." /> : null}
      {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to confirm this instruction."} /> : null}

      <section className="panel compass-next-action">
        <p className="eyebrow">FRESH STEP-UP REQUIRED</p>
        <h2>{amount > 0 ? formatUgx(amount) : "Financial instruction"}</h2>
        <p className="muted">Instruction reference: {intent}</p>
        <p>Enter the six-digit code sent to your registered phone. The code is short-lived and the resulting verification token never needs to be exposed in your browser.</p>
      </section>

      <section className="panel">
        <form action={confirmLongRangeFinancialAction} className="form-grid">
          <input type="hidden" name="intent" value={intent} />
          <input type="hidden" name="purpose" value={purpose} />
          <input type="hidden" name="amount" value={String(amount)} />
          <div className="field">
            <label htmlFor="otp">One-time code</label>
            <input id="otp" name="otp" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" minLength={6} maxLength={6} required autoFocus />
          </div>
          <button className="button" type="submit">Verify and submit to CPay</button>
        </form>

        <form action={resendLongRangeOtpAction} className="form-grid">
          <input type="hidden" name="intent" value={intent} />
          <input type="hidden" name="purpose" value={purpose} />
          <input type="hidden" name="amount" value={String(amount)} />
          <button className="button secondary" type="submit">Send a new code</button>
        </form>
      </section>

      <section className="panel">
        <h2>What happens next</h2>
        <p className="muted">After verification, OpFin submits exactly this governed instruction to CPay using an idempotent reference. A pending provider response remains processing. Funds, balances and participatory-funding totals change only when provider evidence establishes finality and reconciliation agrees.</p>
      </section>
    </Screen>
  );
}
