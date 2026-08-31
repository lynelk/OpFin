import Link from "next/link";
import { startAssetDepositAction } from "@/app/long-range-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }
function amount(value: unknown): number { return typeof value === "number" ? value : Number(value ?? 0); }

export default async function AssetFinancePage() {
  const token = await getAccessToken();
  try {
    const workspace = await longRangeApi.overview(token);
    return (
      <Screen title="Asset finance" description="Track asset-finance requests and, only after independent approval, complete any required deposit with fresh step-up authentication through CPay.">
        <section className="panel compass-next-action">
          <p className="eyebrow">APPROVAL BEFORE PAYMENT</p>
          <h2>No deposit is collected merely because you requested financing.</h2>
          <p className="muted">Approved deposits must exactly match the approved request. OpFin then requires a fresh OTP and submits the instruction to CPay. Provider processing remains visibly separate from settlement.</p>
          <Link className="button secondary" href="/ecosystem">Create or review request details</Link>
        </section>

        <section className="panel">
          <h2>Your asset-finance requests</h2>
          {workspace.asset_finance.length === 0 ? <StateNotice state="empty" message="You have no asset-finance requests yet." /> : (
            <div className="case-list">
              {workspace.asset_finance.map((request) => {
                const deposit = amount(request.deposit_minor);
                const status = text(request.status);
                return (
                  <article className="case-card" key={request.id}>
                    <div className="case-card-head"><strong>{text(request.asset_description)}</strong><span className="badge">{status.replaceAll("_", " ")}</span></div>
                    <div className="grid grid-3"><div><strong>Asset price</strong><p>{formatUgx(amount(request.asset_price_minor))}</p></div><div><strong>Deposit</strong><p>{formatUgx(deposit)}</p></div><div><strong>Term</strong><p>{amount(request.requested_term_months)} months</p></div></div>
                    {status === "approved" && deposit > 0 ? (
                      <form action={startAssetDepositAction} className="form-grid">
                        <input type="hidden" name="source_id" value={request.id} />
                        <input type="hidden" name="amount_minor" value={deposit} />
                        <button className="button" type="submit">Verify and pay approved deposit</button>
                      </form>
                    ) : null}
                    {status === "approved" && deposit === 0 ? <p className="muted">Approved with no deposit collection required.</p> : null}
                    {status === "deposit_settled" ? <p className="muted">Deposit settlement has been confirmed through the governed payment record.</p> : null}
                  </article>
                );
              })}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Asset finance" description="Approved asset-finance deposits appear here."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load asset finance."} /></Screen>;
  }
}
