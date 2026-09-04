import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { linkAccountFocusedAction } from "@/app/focused-long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }

export default async function ConnectedAccountsPage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const workspace = await longRangeApi.overview(token);
    return (
      <Screen title="Connected accounts" description="Connect one financial account at a time. OpFin treats what you enter as unverified until the provider confirms it.">
        {params.status ? <StateNotice state="success" message="Account submitted for verification." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to connect that account."} /> : null}
        <section className="panel">
          <form action={linkAccountFocusedAction} className="form-grid">
            <div className="field"><label htmlFor="account_type">Account type</label><select id="account_type" name="account_type" defaultValue="mobile_money"><option value="mobile_money">Mobile money</option><option value="bank">Bank</option><option value="sacco">SACCO</option><option value="savings">Savings</option><option value="investment">Investment</option><option value="other">Other</option></select></div>
            <div className="field"><label htmlFor="provider">Provider</label><input id="provider" name="provider" placeholder="For example: MTN or your bank" required /></div>
            <div className="field"><label htmlFor="identifier">Phone or account identifier</label><input id="identifier" name="identifier" required autoComplete="off" /></div>
            <button className="button" type="submit">Connect for verification</button>
          </form>
        </section>
        <section className="panel">
          <div className="case-card-head"><h2>Your accounts</h2><Link href="/ecosystem">Back to connected life</Link></div>
          {workspace.linked_accounts.length === 0 ? <p className="muted">No connected accounts yet.</p> : <div className="case-list">{workspace.linked_accounts.map((account) => <article className="case-card" key={account.id}><div className="case-card-head"><strong>{text(account.provider)}</strong><span className="badge">{text(account.status).replaceAll("_", " ")}</span></div><p>{text(account.account_type).replaceAll("_", " ")} · {text(account.masked_identifier)}</p><p className="muted">Data confidence: {text(account.data_confidence).replaceAll("_", " ")}</p></article>)}</div>}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Connected accounts" description="Connect and verify financial accounts."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load connected accounts."} /></Screen>;
  }
}
