"use client";

import { useActionState } from "react";
import { inspectMerchantShadow, type MerchantShadowState } from "@/app/merchant-shadow-actions";

const initial: MerchantShadowState = {
  kind: "idle",
  message: "",
  data: null,
};

const fields = [
  ["borrowerUserId", "Borrower user ID", "number"],
  ["loanProductId", "Merchant finance product ID", "number"],
  ["financialSpaceId", "Authorised business Financial Space ID", "number"],
  ["tenantId", "Stolets merchant tenant reference", "text"],
  ["consentGrantId", "Stolets consent grant reference", "text"],
  ["passportShareId", "Stolets Passport share reference", "text"],
  ["featureSnapshotId", "Trusted commerce feature snapshot reference", "text"],
] as const;

export function ShadowForm() {
  const [state, action, busy] = useActionState(inspectMerchantShadow, initial);

  return (
    <div className="grid grid-2">
      <section className="panel">
        <h2>Review existing consented merchant evidence</h2>
        <p className="muted">
          Read-only check for approved platform operators. This does not approve or price a loan,
          run credit scoring or trigger a payment. OpFin checks borrower, business and lender
          context, then Stolets checks the source permission independently.
        </p>
        <form action={action} className="form-grid">
          {fields.map(([name, label, type]) => (
            <label key={name} className="field">{label}
              <input name={name} type={type} min={type === "number" ? 1 : undefined}
                required autoComplete="off" />
            </label>
          ))}
          <button className="button" type="submit" disabled={busy}>
            {busy ? "Verifying evidence…" : "Inspect in shadow mode"}
          </button>
        </form>
        {state.kind !== "idle" && (
          <p role="status" aria-live="polite" className="muted">{state.message}</p>
        )}
      </section>
      <section className="panel">
        <h2>Shadow observations</h2>
        {!state.data ? (
          <p className="muted">No verified snapshot is loaded. Missing or revoked consent does not become an adverse credit decision.</p>
        ) : (
          <>
            <p><strong>Manual review only.</strong> No customer offer or lending authority is implied.</p>
            <dl>
              <dt>Decision</dt><dd>{state.data.decision.replaceAll("_", " ")}</dd>
              <dt>Observation window ends</dt><dd>{state.data.observation_window_end}</dd>
              <dt>Net sales from trusted records</dt><dd>{state.data.indicators.netSalesMinor.toLocaleString("en-UG")} {state.data.indicators.currency}</dd>
              <dt>Transactions / evidence days</dt><dd>{state.data.indicators.transactionCount} / {state.data.indicators.dataCoverageDays}</dd>
              <dt>Data quality / reconciliation</dt><dd>{(state.data.indicators.dataQualityBps / 100).toFixed(1)}% / {(state.data.indicators.reconciliationBps / 100).toFixed(1)}%</dd>
              <dt>Lender authority</dt><dd>{state.data.lender_authority_status.replaceAll("_", " ")}</dd>
              <dt>Consent</dt><dd>{state.data.customer_consent_status.replaceAll("_", " ")}</dd>
            </dl>
            <h3>Quality review</h3>
            <ul>{(state.data.quality_reason_codes.length ? state.data.quality_reason_codes : ["No automatic quality flags; independent review is still required."]).map(x => <li key={x}>{x.replaceAll("_", " ")}</li>)}</ul>
            <h3>What this cannot prove</h3>
            <ul>{state.data.limitations.map((limitation) => <li key={limitation}>{limitation}</li>)}</ul>
          </>
        )}
      </section>
    </div>
  );
}
