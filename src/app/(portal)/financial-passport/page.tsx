import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError } from "@/lib/api/errors";
import { v5P0Api } from "@/lib/api/v5-p0";
import { formatUgx } from "@/lib/format";

export default async function FinancialPassportPage() {
  const token = await getAccessToken();
  try {
    const response = await v5P0Api.passport(token);
    const passport = response.data;
    const position = passport.content.financial_position;
    return (
      <Screen title="Financial Passport" description="A provenance-labelled snapshot of the financial and identity information OpFin can actually substantiate.">
        <section className="panel">
          <div className="case-card-head">
            <div><p className="eyebrow">Snapshot</p><h2>Your current financial record</h2></div>
            <span className="badge">Confidence: {passport.confidence}</span>
          </div>
          <div className="grid grid-3">
            <div><span className="muted">Recorded accounts</span><div className="stat">{position.recorded_accounts}</div></div>
            <div><span className="muted">Recorded balance</span><div className="stat">{formatUgx(position.recorded_balance_minor)}</div></div>
            <div><span className="muted">Outstanding debt</span><div className="stat">{formatUgx(position.outstanding_debt_minor)}</div></div>
          </div>
          <p className="muted">Generated {new Date(passport.content.generated_at).toLocaleString("en-UG")} · Snapshot hash {passport.content_hash.slice(0, 16)}…</p>
        </section>
        <section className="panel">
          <h2>Where this information comes from</h2>
          <div className="grid grid-2">
            {Object.entries(passport.provenance).map(([key, source]) => (
              <article className="case-card" key={key}><strong>{key.replaceAll("_", " ")}</strong><p className="muted">{source.replaceAll("_", " ")}</p></article>
            ))}
          </div>
          <p className="muted">This passport labels provenance and confidence rather than presenting inferred information as fact.</p>
        </section>
        <section className="panel">
          <div className="grid grid-2">
            <Link className="button secondary" href="/kyc">Review identity verification</Link>
            <Link className="button secondary" href="/consent">Review data permissions</Link>
          </div>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Financial Passport" description="Your provenance-labelled financial snapshot will appear here."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load Financial Passport."} /></Screen>;
  }
}
