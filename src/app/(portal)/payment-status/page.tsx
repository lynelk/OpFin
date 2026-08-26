import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { OpfinApiError } from "@/lib/api/errors";
import { v5P0Api } from "@/lib/api/v5-p0";

export default async function PaymentStatusPage() {
  const token = await getAccessToken();
  try {
    const response = await v5P0Api.reconciliation(token);
    const summary = response.data;
    return (
      <Screen title="Payment Status" description="See whether OpFin money-movement records agree with governed CPay evidence, without exposing provider-specific payment rails in OpFin.">
        <section className="panel">
          <div className="grid grid-4">
            <div><span className="muted">Transactions</span><div className="stat">{summary.total}</div></div>
            <div><span className="muted">Matched</span><div className="stat">{summary.matched}</div></div>
            <div><span className="muted">Open</span><div className="stat">{summary.open}</div></div>
            <div><span className="muted">Mismatch</span><div className="stat">{summary.mismatch}</div></div>
          </div>
          <p className="muted">Payment execution remains owned by CPay. OpFin records business meaning and reconciliation state.</p>
        </section>
        <section className="panel">
          <h2>Recent reconciliation items</h2>
          {summary.items.length === 0 ? <p className="muted">No reconciliation exceptions are currently visible on your account.</p> : (
            <div className="case-list">
              {summary.items.map((item, index) => (
                <article className="case-card" key={String(item.id ?? index)}>
                  <div className="case-card-head"><strong>{String(item.direction ?? item.type ?? "Payment")}</strong><span className="badge">{String(item.reconciliation_status ?? item.status ?? "unknown")}</span></div>
                  <p className="muted">Reference: {String(item.provider_reference ?? item.reference ?? item.id ?? "not supplied")}</p>
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Payment Status" description="Your reconciled CPay payment status will appear here."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load payment status."} /></Screen>;
  }
}
