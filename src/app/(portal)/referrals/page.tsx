import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { formatUgx } from "@/lib/format";
import { createReferralFocusedAction } from "@/app/focused-long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }

export default async function ReferralsPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const workspace = await longRangeApi.overview(token);
    return (
      <Screen title="Referrals" description="Invite people without turning referrals into instant cash. Rewards are posted only after eligibility and anti-abuse controls pass.">
        {params.status ? <StateNotice state="success" message="Referral recorded." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to record that referral."} /> : null}
        <section className="panel">
          <form action={createReferralFocusedAction} className="form-grid">
            <div className="field"><label htmlFor="referred_user_id">OpFin user ID, if the person is already registered</label><input id="referred_user_id" name="referred_user_id" type="number" min="1" /></div>
            <button className="button" type="submit">Record referral</button>
          </form>
        </section>
        <section className="panel">
          <div className="case-card-head"><h2>Your referrals</h2><Link href="/ecosystem">Back to connected life</Link></div>
          {workspace.referrals.length === 0 ? <p className="muted">No referrals recorded.</p> : <div className="case-list">{workspace.referrals.map((referral) => <article className="case-card" key={referral.id}><div className="case-card-head"><strong>{text(referral.referral_code)}</strong><span className="badge">{text(referral.status).replaceAll("_", " ")}</span></div><p className="muted">Reward: {formatUgx(amount(referral.reward_minor))}</p></article>)}</div>}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Referrals" description="Manage referral activity."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load referrals."} /></Screen>;
  }
}
