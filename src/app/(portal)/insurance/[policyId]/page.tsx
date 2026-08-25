import { randomUUID } from "node:crypto";
import Link from "next/link";
import {
  disputeProtectionClaimAction,
  payProtectionPremiumAction,
  submitProtectionClaimAction
} from "@/app/save-protection-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { saveProtectionApi } from "@/lib/api/save-protection";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

function feedback(status?: string): string | undefined {
  switch (status) {
    case "enrolled": return "Enrollment recorded from the accepted disclosure. Premium payment and insurer issuance are still required before cover can be active.";
    case "premium-pending": return "Premium collection was initiated through CPay. The payment and insurer settlement states remain separate, and cover is not activated by payment success alone.";
    case "claim-submitted": return "Claim submitted into the insurer or underwriter workflow. OpFin tracks the case but does not make the claim decision.";
    case "claim-disputed": return "Claim dispute recorded and returned to the insurer or underwriter workflow for reconsideration.";
    default: return undefined;
  }
}

function canPayPremium(status: string): boolean {
  return !["cancelled", "expired", "pending_issuance"].includes(status);
}

export default async function ProtectionPolicyPage({
  params,
  searchParams
}: {
  params: Promise<{ policyId: string }>;
  searchParams?: Promise<{ status?: string; error?: string; message?: string }>;
}) {
  const route = await params;
  const query = await searchParams;
  const policyId = Number(route.policyId);
  const token = await getAccessToken();

  if (!Number.isInteger(policyId) || policyId <= 0) {
    return (
      <Screen title="Protection policy" description="Review insurer-issued cover and servicing activity.">
        <StateNotice state="validation" message="The selected protection policy is invalid." />
        <Link className="button secondary" href="/insurance">Back to protection</Link>
      </Screen>
    );
  }

  try {
    const response = await saveProtectionApi.protectionPolicy(policyId, token);
    const policy = response.data.policy;
    const product = policy.product;
    const premiums = policy.premium_payments ?? [];
    const claims = policy.claims ?? [];
    const notice = query?.message ?? feedback(query?.status);
    const premiumKey = `protection-premium-${randomUUID()}`;

    return (
      <Screen
        title={product.name}
        description="Track disclosure acceptance, premium collection, insurer settlement, policy issuance and claims as separate controlled states."
        action={<Link className="button secondary" href="/insurance">Back to protection</Link>}
      >
        {notice ? <StateNotice state={query?.error ? (query.error === "validation" ? "validation" : "server") : "success"} message={notice} /> : null}

        <div className="grid grid-3" style={{ marginTop: notice ? 16 : 0 }}>
          <section className="panel">
            <p className="eyebrow">Policy state</p>
            <div className="stat stat-text">{policy.status}</div>
            <p className="muted">Only an issued policy with an active cover period is presented as active cover.</p>
          </section>
          <section className="panel">
            <p className="eyebrow">Premium</p>
            <div className="stat">{formatUgx(policy.premium_amount_minor)}</div>
            <p className="muted">{policy.premium_frequency}. Collection runs through CPay, then requires insurer settlement evidence.</p>
          </section>
          <section className="panel">
            <p className="eyebrow">Cover limit</p>
            <div className="stat">{policy.coverage_limit_minor ? formatUgx(policy.coverage_limit_minor) : "See terms"}</div>
            <p className="muted">Benefits, exclusions and claim decisions remain governed by the insurer or underwriter terms.</p>
          </section>
        </div>

        <div className="grid grid-2" style={{ marginTop: 16 }}>
          <section className="panel">
            <div className="case-card-head">
              <div>
                <p className="eyebrow">Policy record</p>
                <h2>{policy.external_policy_number ?? policy.policy_reference}</h2>
              </div>
              <span className={`badge ${policy.status === "active" ? "ok" : "warn"}`}>{policy.status}</span>
            </div>
            <table className="table">
              <tbody>
                <tr><th>OpFin reference</th><td>{policy.policy_reference}</td></tr>
                <tr><th>External policy number</th><td>{policy.external_policy_number ?? "Awaiting insurer issuance"}</td></tr>
                <tr><th>Insurer</th><td>{product.insurer_name}</td></tr>
                <tr><th>Underwriter</th><td>{product.underwriter_name ?? "As disclosed by insurer"}</td></tr>
                <tr><th>Cover starts</th><td>{policy.cover_start_date ?? "Not issued"}</td></tr>
                <tr><th>Cover ends</th><td>{policy.cover_end_date ?? "Not issued"}</td></tr>
                <tr><th>Next premium due</th><td>{policy.next_premium_due_date ?? "Not scheduled"}</td></tr>
                <tr><th>Disclosure version</th><td>{product.disclosure_version}</td></tr>
                <tr><th>Accepted disclosure hash</th><td><code>{policy.disclosure_hash.slice(0, 16)}…</code></td></tr>
              </tbody>
            </table>
            {product.terms_url ? <a className="button secondary" href={product.terms_url}>Read controlled policy terms</a> : null}
          </section>

          <section className="panel">
            <h2>Premium payment</h2>
            {canPayPremium(policy.status) ? (
              <form action={payProtectionPremiumAction} className="form-grid">
                <input type="hidden" name="policy_id" value={policy.id} />
                <input type="hidden" name="idempotency_key" value={premiumKey} />
                <table className="table">
                  <tbody>
                    <tr><th>Amount</th><td>{formatUgx(policy.premium_amount_minor)}</td></tr>
                    <tr><th>Frequency</th><td>{policy.premium_frequency}</td></tr>
                    <tr><th>Collection route</th><td>CPay payment orchestration</td></tr>
                    <tr><th>Risk owner</th><td>{product.insurer_name}</td></tr>
                  </tbody>
                </table>
                <p className="muted">Payment success is not policy issuance. After collection, insurer settlement evidence and any required issuance action must still complete.</p>
                <button className="button" type="submit">Initiate premium payment</button>
              </form>
            ) : policy.status === "pending_issuance" ? (
              <StateNotice state="empty" message="Premium settlement is confirmed and the policy is awaiting insurer issuance. Another premium is not requested in this state." />
            ) : (
              <StateNotice state="empty" message={`Premium collection is unavailable while this policy is ${policy.status}.`} />
            )}
          </section>
        </div>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Premium history</h2>
          {premiums.length ? (
            <table className="table">
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>Amount</th>
                  <th>Premium state</th>
                  <th>Payment rail</th>
                  <th>Coverage period</th>
                  <th>Insurer settlement</th>
                </tr>
              </thead>
              <tbody>
                {premiums.map((premium) => (
                  <tr key={premium.id}>
                    <td>{premium.payment_reference}</td>
                    <td>{formatUgx(premium.amount_minor)}</td>
                    <td><span className={`badge ${premium.status === "confirmed" ? "ok" : "warn"}`}>{premium.status}</span></td>
                    <td>{premium.mobile_money_transaction ? `${premium.mobile_money_transaction.provider}: ${premium.mobile_money_transaction.status}` : "Not linked"}</td>
                    <td>{premium.coverage_period_start && premium.coverage_period_end ? `${premium.coverage_period_start} to ${premium.coverage_period_end}` : "Determined by insurer workflow"}</td>
                    <td>{premium.partner_reference ?? (premium.partner_confirmed_at ? "Confirmed" : "Awaiting partner")}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <StateNotice state="empty" message="No premium payment has been recorded for this policy." />
          )}
        </section>

        <div className="grid grid-2" style={{ marginTop: 16 }}>
          <section className="panel">
            <h2>Submit a claim</h2>
            {policy.status === "active" ? (
              <form action={submitProtectionClaimAction} className="form-grid">
                <input type="hidden" name="policy_id" value={policy.id} />
                <div className="field">
                  <label htmlFor="incident_date">Incident date</label>
                  <input id="incident_date" name="incident_date" type="date" max={new Date().toISOString().slice(0, 10)} required />
                </div>
                <div className="field">
                  <label htmlFor="category">Claim category</label>
                  <input id="category" name="category" minLength={2} maxLength={80} required placeholder="Medical, device, asset..." />
                </div>
                <div className="field">
                  <label htmlFor="claimed_amount_minor">Claimed amount (UGX, if applicable)</label>
                  <input id="claimed_amount_minor" name="claimed_amount_minor" type="number" min={0} step={1} />
                </div>
                <div className="field">
                  <label htmlFor="description">What happened?</label>
                  <textarea id="description" name="description" minLength={10} maxLength={4000} required rows={5} />
                </div>
                <div className="field">
                  <label htmlFor="evidence">Evidence references, one per line</label>
                  <textarea id="evidence" name="evidence" rows={4} placeholder="Document reference, secure upload reference, receipt hash..." />
                </div>
                <p className="muted">The claim is sent into the disclosed insurer or underwriter workflow. OpFin can track evidence and status but does not approve or decline the claim.</p>
                <button className="button" type="submit">Submit claim to partner workflow</button>
              </form>
            ) : (
              <StateNotice state="empty" message="Claims can be submitted from this screen only while the policy is active. Contact support if the displayed policy state is incorrect." />
            )}
          </section>

          <section className="panel">
            <h2>Benefits and exclusions</h2>
            <p className="eyebrow">Benefits</p>
            {product.benefits?.length ? <ul>{product.benefits.map((benefit) => <li key={benefit}>{benefit}</li>)}</ul> : <p className="muted">See controlled terms.</p>}
            <p className="eyebrow">Exclusions</p>
            {product.exclusions?.length ? <ul>{product.exclusions.map((exclusion) => <li key={exclusion}>{exclusion}</li>)}</ul> : <p className="muted">See controlled terms for exclusions.</p>}
            <StateNotice state="empty" message={`Claim decision authority: ${product.insurer_name}${product.underwriter_name ? ` / ${product.underwriter_name}` : ""}. OpFin does not replace that authority.`} />
          </section>
        </div>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Claims</h2>
          {claims.length ? (
            <div className="case-list">
              {claims.map((claim) => (
                <div className="case-card" key={claim.id}>
                  <div className="case-card-head">
                    <div>
                      <p className="eyebrow">{claim.claim_reference}</p>
                      <h3>{claim.category}</h3>
                    </div>
                    <span className={`badge ${claim.status === "paid" || claim.status === "approved" ? "ok" : "warn"}`}>{claim.status}</span>
                  </div>
                  <table className="table">
                    <tbody>
                      <tr><th>Incident date</th><td>{claim.incident_date}</td></tr>
                      <tr><th>Claimed</th><td>{claim.claimed_amount_minor != null ? formatUgx(claim.claimed_amount_minor) : "Not specified"}</td></tr>
                      <tr><th>Approved</th><td>{claim.approved_amount_minor != null ? formatUgx(claim.approved_amount_minor) : "Not decided"}</td></tr>
                      <tr><th>Partner claim reference</th><td>{claim.partner_claim_reference ?? "Awaiting partner"}</td></tr>
                      <tr><th>Evidence references</th><td>{claim.evidence?.length ?? 0}</td></tr>
                      <tr><th>Decision reason</th><td>{claim.decision_reason ?? "No final decision recorded"}</td></tr>
                    </tbody>
                  </table>
                  <p className="muted">{claim.description}</p>
                  {claim.status === "declined" ? (
                    <form action={disputeProtectionClaimAction} className="form-grid" style={{ marginTop: 12 }}>
                      <input type="hidden" name="policy_id" value={policy.id} />
                      <input type="hidden" name="claim_id" value={claim.id} />
                      <div className="field">
                        <label htmlFor={`reason-${claim.id}`}>Reason for dispute</label>
                        <textarea id={`reason-${claim.id}`} name="reason" minLength={10} maxLength={2000} required rows={4} />
                      </div>
                      <button className="button secondary" type="submit">Request insurer reconsideration</button>
                    </form>
                  ) : null}
                </div>
              ))}
            </div>
          ) : (
            <StateNotice state="empty" message="No claims have been submitted for this policy." />
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load protection policy.";

    return (
      <Screen title="Protection policy" description="Review insurer-issued cover and servicing activity.">
        <StateNotice state={state} message={message} />
        <Link className="button secondary" href="/insurance">Back to protection</Link>
      </Screen>
    );
  }
}
