import {
  activateProtectionProductAction,
  activateSavingsProductAction,
  confirmProtectionPremiumAction,
  confirmSavingsContributionAction,
  createProtectionProductAction,
  createSavingsProductAction,
  issueProtectionPolicyAction,
  releaseSavingsWithdrawalAction,
  retrySavingsWithdrawalPayoutAction,
  updateProtectionClaimAction
} from "@/app/save-protection-operations-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { saveProtectionOperationsApi } from "@/lib/api/save-protection-operations";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";
import type { ClaimQueueItem } from "@/lib/save-protection/operations-types";

function feedback(status?: string): string | undefined {
  const messages: Record<string, string> = {
    "savings-product-created": "Savings product draft created. A different operations user must independently approve it before activation.",
    "savings-product-activated": "Savings product independently approved and activated.",
    "savings-contribution-confirmed": "Partner settlement evidence recorded and the savings contribution moved into the confirmed partner position.",
    "savings-withdrawal-released": "Partner withdrawal release recorded and CPay payout initiated.",
    "savings-payout-retried": "Partner-released withdrawal payout retried through the payment orchestration boundary.",
    "protection-product-created": "Protection product draft created. Independent approval is required before customers can see it.",
    "protection-product-activated": "Protection product independently approved and activated.",
    "protection-premium-confirmed": "Insurer premium settlement evidence recorded. Policy issuance remains a separate step.",
    "protection-policy-issued": "Insurer policy issuance and cover period recorded.",
    "protection-claim-updated": "Insurer or underwriter claim state recorded."
  };
  return status ? messages[status] : undefined;
}

function claimOptions(claim: ClaimQueueItem): string[] {
  if (claim.status === "submitted" || claim.status === "disputed") return ["partner_review"];
  if (claim.status === "partner_review") return ["approved", "declined"];
  if (claim.status === "approved") return ["paid", "closed"];
  return [];
}

export default async function SaveProtectionOperationsPage({
  searchParams
}: {
  searchParams?: Promise<{ status?: string; error?: string; message?: string }>;
}) {
  const query = await searchParams;
  const token = await getAccessToken();

  try {
    const [queueResponse, savingsProductsResponse, protectionProductsResponse] = await Promise.all([
      saveProtectionOperationsApi.workQueue(token),
      saveProtectionOperationsApi.savingsProducts(token),
      saveProtectionOperationsApi.protectionProducts(token)
    ]);
    const queue = queueResponse.data;
    const savingsProducts = savingsProductsResponse.data.products;
    const protectionProducts = protectionProductsResponse.data.products;
    const notice = query?.message ?? feedback(query?.status);

    return (
      <Screen
        title="Save & Protection operations"
        description="Operate product approval, partner settlement, payout release, policy issuance and insurer claim states without collapsing payment evidence into product truth."
      >
        {notice ? <StateNotice state={query?.error ? (query.error === "validation" ? "validation" : "server") : "success"} message={notice} /> : null}

        <div className="grid grid-3" style={{ marginTop: notice ? 16 : 0 }}>
          <section className="panel"><p className="eyebrow">Savings confirmations</p><div className="stat">{queue.counts.savings_contributions}</div></section>
          <section className="panel"><p className="eyebrow">Savings withdrawals</p><div className="stat">{queue.counts.savings_withdrawals}</div></section>
          <section className="panel"><p className="eyebrow">Premium settlements</p><div className="stat">{queue.counts.protection_premiums}</div></section>
          <section className="panel"><p className="eyebrow">Policy issuance</p><div className="stat">{queue.counts.protection_policies}</div></section>
          <section className="panel"><p className="eyebrow">Claims</p><div className="stat">{queue.counts.protection_claims}</div></section>
          <section className="panel"><p className="eyebrow">Queue scope</p><div className="stat stat-text">{queue.scope}</div><p className="muted">{queue.institution_id ? `Institution ${queue.institution_id}` : "Platform-wide"}</p></section>
        </div>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Savings partner confirmations</h2>
          {queue.savings_contributions.length ? (
            <div className="case-list">
              {queue.savings_contributions.map((movement) => (
                <div className="case-card" key={movement.id}>
                  <div className="case-card-head">
                    <div><p className="eyebrow">{movement.movement_reference}</p><h3>{movement.goal?.name ?? "Savings contribution"}</h3></div>
                    <span className="badge warn">{movement.status}</span>
                  </div>
                  <p className="muted">{formatUgx(movement.amount_minor)} · payment rail {movement.mobile_money_transaction?.provider ?? "unknown"}: {movement.mobile_money_transaction?.status ?? "not linked"}</p>
                  <form action={confirmSavingsContributionAction} className="form-grid" style={{ marginTop: 12 }}>
                    <input type="hidden" name="movement_id" value={movement.id} />
                    <div className="field"><label htmlFor={`sav-ref-${movement.id}`}>Partner settlement reference</label><input id={`sav-ref-${movement.id}`} name="partner_reference" required maxLength={160} /></div>
                    <div className="field"><label htmlFor={`sav-hash-${movement.id}`}>Partner evidence SHA-256</label><input id={`sav-hash-${movement.id}`} name="partner_evidence_hash" required minLength={64} maxLength={64} pattern="[A-Fa-f0-9]{64}" /></div>
                    <button className="button" type="submit">Confirm partner position</button>
                  </form>
                </div>
              ))}
            </div>
          ) : <StateNotice state="empty" message="No collected savings contribution is awaiting partner confirmation." />}
        </section>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Savings withdrawals</h2>
          {queue.savings_withdrawals.length ? (
            <div className="case-list">
              {queue.savings_withdrawals.map((movement) => (
                <div className="case-card" key={movement.id}>
                  <div className="case-card-head">
                    <div><p className="eyebrow">{movement.movement_reference}</p><h3>{movement.goal?.name ?? "Savings withdrawal"}</h3></div>
                    <span className="badge warn">{movement.status}</span>
                  </div>
                  <p className="muted">{formatUgx(movement.amount_minor)} · requested {new Date(movement.requested_at).toLocaleString()}</p>
                  {movement.status === "withdrawal_requested" ? (
                    <form action={releaseSavingsWithdrawalAction} className="form-grid" style={{ marginTop: 12 }}>
                      <input type="hidden" name="movement_id" value={movement.id} />
                      <div className="field"><label htmlFor={`wd-ref-${movement.id}`}>Partner release reference</label><input id={`wd-ref-${movement.id}`} name="partner_reference" required maxLength={160} /></div>
                      <div className="field"><label htmlFor={`wd-hash-${movement.id}`}>Partner release evidence SHA-256</label><input id={`wd-hash-${movement.id}`} name="partner_evidence_hash" required minLength={64} maxLength={64} pattern="[A-Fa-f0-9]{64}" /></div>
                      <button className="button" type="submit">Record release and initiate payout</button>
                    </form>
                  ) : (
                    <form action={retrySavingsWithdrawalPayoutAction} className="inline-form" style={{ marginTop: 12 }}>
                      <input type="hidden" name="movement_id" value={movement.id} />
                      <button className="button secondary" type="submit">Retry CPay payout</button>
                    </form>
                  )}
                </div>
              ))}
            </div>
          ) : <StateNotice state="empty" message="No savings withdrawal currently requires partner release or payout retry." />}
        </section>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Insurer premium settlement</h2>
          {queue.protection_premiums.length ? (
            <div className="case-list">
              {queue.protection_premiums.map((premium) => (
                <div className="case-card" key={premium.id}>
                  <div className="case-card-head">
                    <div><p className="eyebrow">{premium.payment_reference}</p><h3>{premium.policy?.product?.name ?? "Protection premium"}</h3></div>
                    <span className="badge warn">{premium.status}</span>
                  </div>
                  <p className="muted">{formatUgx(premium.amount_minor)} · {premium.policy?.product?.insurer_name ?? "Insurer"}</p>
                  <form action={confirmProtectionPremiumAction} className="form-grid" style={{ marginTop: 12 }}>
                    <input type="hidden" name="payment_id" value={premium.id} />
                    <div className="field"><label htmlFor={`premium-ref-${premium.id}`}>Insurer settlement reference</label><input id={`premium-ref-${premium.id}`} name="partner_reference" required maxLength={160} /></div>
                    <div className="field"><label htmlFor={`premium-hash-${premium.id}`}>Settlement evidence SHA-256</label><input id={`premium-hash-${premium.id}`} name="partner_evidence_hash" required minLength={64} maxLength={64} pattern="[A-Fa-f0-9]{64}" /></div>
                    <button className="button" type="submit">Confirm insurer settlement</button>
                  </form>
                </div>
              ))}
            </div>
          ) : <StateNotice state="empty" message="No collected premium is awaiting insurer settlement confirmation." />}
        </section>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Policy issuance</h2>
          {queue.protection_policies.length ? (
            <div className="case-list">
              {queue.protection_policies.map((policy) => (
                <div className="case-card" key={policy.id}>
                  <div className="case-card-head">
                    <div><p className="eyebrow">{policy.policy_reference}</p><h3>{policy.product?.name ?? "Protection policy"}</h3></div>
                    <span className="badge warn">{policy.status}</span>
                  </div>
                  <p className="muted">{policy.product?.insurer_name ?? "Insurer"} · premium {formatUgx(policy.premium_amount_minor)}</p>
                  <form action={issueProtectionPolicyAction} className="form-grid" style={{ marginTop: 12 }}>
                    <input type="hidden" name="policy_id" value={policy.id} />
                    <div className="field"><label htmlFor={`policy-num-${policy.id}`}>External policy number</label><input id={`policy-num-${policy.id}`} name="external_policy_number" required maxLength={160} /></div>
                    <div className="field"><label htmlFor={`policy-ref-${policy.id}`}>Insurer issuance reference</label><input id={`policy-ref-${policy.id}`} name="partner_reference" required maxLength={160} /></div>
                    <div className="grid grid-2">
                      <div className="field"><label htmlFor={`cover-start-${policy.id}`}>Cover start</label><input id={`cover-start-${policy.id}`} name="cover_start_date" type="date" required /></div>
                      <div className="field"><label htmlFor={`cover-end-${policy.id}`}>Cover end</label><input id={`cover-end-${policy.id}`} name="cover_end_date" type="date" required /></div>
                    </div>
                    <button className="button" type="submit">Record insurer issuance</button>
                  </form>
                </div>
              ))}
            </div>
          ) : <StateNotice state="empty" message="No protection policy is awaiting insurer issuance." />}
        </section>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Claims partner workflow</h2>
          {queue.protection_claims.length ? (
            <div className="case-list">
              {queue.protection_claims.map((claim) => {
                const nextStates = claimOptions(claim);
                return (
                  <div className="case-card" key={claim.id}>
                    <div className="case-card-head">
                      <div><p className="eyebrow">{claim.claim_reference}</p><h3>{claim.policy?.product?.name ?? claim.category}</h3></div>
                      <span className="badge warn">{claim.status}</span>
                    </div>
                    <p>{claim.description}</p>
                    <p className="muted">Claimed {claim.claimed_amount_minor != null ? formatUgx(claim.claimed_amount_minor) : "amount not specified"} · insurer {claim.policy?.product?.insurer_name ?? "as recorded on policy"}</p>
                    {nextStates.length ? (
                      <form action={updateProtectionClaimAction} className="form-grid" style={{ marginTop: 12 }}>
                        <input type="hidden" name="claim_id" value={claim.id} />
                        <div className="field"><label htmlFor={`claim-status-${claim.id}`}>Partner state</label><select id={`claim-status-${claim.id}`} name="status" required defaultValue=""><option value="" disabled>Select next valid state</option>{nextStates.map((state) => <option value={state} key={state}>{state}</option>)}</select></div>
                        <div className="field"><label htmlFor={`claim-ref-${claim.id}`}>Partner claim reference</label><input id={`claim-ref-${claim.id}`} name="partner_claim_reference" maxLength={160} defaultValue={claim.partner_claim_reference ?? ""} /></div>
                        <div className="field"><label htmlFor={`approved-${claim.id}`}>Approved amount (UGX, when approved)</label><input id={`approved-${claim.id}`} name="approved_amount_minor" type="number" min={0} step={1} /></div>
                        <div className="field"><label htmlFor={`reason-${claim.id}`}>Partner decision / review note</label><textarea id={`reason-${claim.id}`} name="decision_reason" rows={4} maxLength={3000} defaultValue={claim.decision_reason ?? ""} /></div>
                        <button className="button" type="submit">Record partner state</button>
                      </form>
                    ) : <StateNotice state="empty" message="No supported next claim state is exposed for this queue item." />}
                  </div>
                );
              })}
            </div>
          ) : <StateNotice state="empty" message="No protection claim currently requires insurer or underwriter action." />}
        </section>

        <div className="grid grid-2" style={{ marginTop: 16 }}>
          <section className="panel">
            <h2>Create savings product draft</h2>
            <form action={createSavingsProductAction} className="form-grid">
              <div className="grid grid-2">
                <div className="field"><label htmlFor="sav-code">Code</label><input id="sav-code" name="code" required maxLength={64} /></div>
                <div className="field"><label htmlFor="sav-name">Name</label><input id="sav-name" name="name" required maxLength={160} /></div>
              </div>
              <div className="field"><label htmlFor="sav-partner">Regulated savings partner</label><input id="sav-partner" name="partner_name" required maxLength={160} /></div>
              <div className="field"><label htmlFor="sav-partner-ref">Partner product reference</label><input id="sav-partner-ref" name="partner_product_reference" required maxLength={160} /></div>
              <div className="grid grid-2">
                <div className="field"><label htmlFor="sav-type">Product type</label><select id="sav-type" name="product_type" defaultValue="goal" required><option value="goal">Goal</option><option value="emergency">Emergency</option><option value="notice">Notice</option><option value="group">Group</option><option value="sacco">SACCO</option><option value="employer">Employer</option></select></div>
                <div className="field"><label htmlFor="sav-terms-version">Terms version</label><input id="sav-terms-version" name="terms_version" required maxLength={64} /></div>
              </div>
              <input type="hidden" name="country_code" value="UG" /><input type="hidden" name="currency" value="UGX" />
              <div className="grid grid-2">
                <div className="field"><label htmlFor="sav-min">Minimum contribution (UGX)</label><input id="sav-min" name="minimum_contribution_minor" type="number" min={0} step={1} /></div>
                <div className="field"><label htmlFor="sav-max">Maximum contribution (UGX)</label><input id="sav-max" name="maximum_contribution_minor" type="number" min={1} step={1} /></div>
                <div className="field"><label htmlFor="sav-min-wd">Minimum withdrawal (UGX)</label><input id="sav-min-wd" name="minimum_withdrawal_minor" type="number" min={0} step={1} /></div>
                <div className="field"><label htmlFor="sav-notice">Notice days</label><input id="sav-notice" name="notice_days" type="number" min={0} max={3650} step={1} /></div>
                <div className="field"><label htmlFor="sav-lock">Lock days</label><input id="sav-lock" name="lock_days" type="number" min={0} max={3650} step={1} /></div>
                <div className="field"><label htmlFor="sav-terms-url">Controlled terms URL</label><input id="sav-terms-url" name="terms_url" type="url" required /></div>
              </div>
              <div className="field"><label htmlFor="sav-disclosures">Customer disclosures, one per line</label><textarea id="sav-disclosures" name="disclosures" required rows={4} /></div>
              <p className="muted">The product is always created as draft and custody is forced to partner-held. A different operations user must activate it.</p>
              <button className="button" type="submit">Create savings draft</button>
            </form>
          </section>

          <section className="panel">
            <h2>Create protection product draft</h2>
            <form action={createProtectionProductAction} className="form-grid">
              <div className="grid grid-2">
                <div className="field"><label htmlFor="prt-code">Code</label><input id="prt-code" name="code" required maxLength={64} /></div>
                <div className="field"><label htmlFor="prt-name">Name</label><input id="prt-name" name="name" required maxLength={160} /></div>
              </div>
              <div className="field"><label htmlFor="prt-insurer">Insurer</label><input id="prt-insurer" name="insurer_name" required maxLength={160} /></div>
              <div className="field"><label htmlFor="prt-underwriter">Underwriter</label><input id="prt-underwriter" name="underwriter_name" maxLength={160} /></div>
              <div className="field"><label htmlFor="prt-ref">Partner product reference</label><input id="prt-ref" name="partner_product_reference" required maxLength={160} /></div>
              <div className="grid grid-2">
                <div className="field"><label htmlFor="prt-type">Product type</label><select id="prt-type" name="product_type" defaultValue="health" required><option value="micro">Micro</option><option value="loan">Loan</option><option value="health">Health</option><option value="event">Event</option><option value="device">Device</option><option value="asset">Asset</option></select></div>
                <div className="field"><label htmlFor="prt-frequency">Premium frequency</label><select id="prt-frequency" name="premium_frequency" defaultValue="monthly" required><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annual">Annual</option><option value="one_off">One off</option></select></div>
              </div>
              <input type="hidden" name="country_code" value="UG" /><input type="hidden" name="currency" value="UGX" />
              <div className="grid grid-2">
                <div className="field"><label htmlFor="prt-premium">Premium (UGX)</label><input id="prt-premium" name="premium_amount_minor" type="number" min={1} step={1} required /></div>
                <div className="field"><label htmlFor="prt-limit">Coverage limit (UGX)</label><input id="prt-limit" name="coverage_limit_minor" type="number" min={0} step={1} /></div>
              </div>
              <div className="field"><label htmlFor="prt-disclosure-version">Disclosure version</label><input id="prt-disclosure-version" name="disclosure_version" required maxLength={64} /></div>
              <div className="field"><label htmlFor="prt-benefits">Benefits, one per line</label><textarea id="prt-benefits" name="benefits" required rows={3} /></div>
              <div className="field"><label htmlFor="prt-exclusions">Exclusions, one per line</label><textarea id="prt-exclusions" name="exclusions" required rows={3} /></div>
              <div className="field"><label htmlFor="prt-claims">Claims disclosure</label><textarea id="prt-claims" name="claims_disclosure" required minLength={10} rows={3} /></div>
              <div className="field"><label htmlFor="prt-terms">Controlled terms URL</label><input id="prt-terms" name="terms_url" type="url" required /></div>
              <p className="muted">The draft explicitly records insurer/underwriter ownership. A separate checker must approve activation.</p>
              <button className="button" type="submit">Create protection draft</button>
            </form>
          </section>
        </div>

        <div className="grid grid-2" style={{ marginTop: 16 }}>
          <section className="panel">
            <h2>Savings product approvals</h2>
            {savingsProducts.length ? <div className="case-list">{savingsProducts.map((product) => (
              <div className="case-card" key={product.id}>
                <div className="case-card-head"><div><p className="eyebrow">{product.code}</p><h3>{product.name}</h3></div><span className={`badge ${product.status === "active" ? "ok" : "warn"}`}>{product.status}</span></div>
                <p className="muted">Partner: {product.partner_name} · author user {product.created_by ?? "unknown"} · approver {product.approved_by ?? "pending"}</p>
                {product.status !== "active" && product.status !== "retired" ? (
                  <form action={activateSavingsProductAction} className="form-grid" style={{ marginTop: 12 }}>
                    <input type="hidden" name="product_id" value={product.id} />
                    <div className="field"><label htmlFor={`sav-ap-ref-${product.id}`}>Approval reference</label><input id={`sav-ap-ref-${product.id}`} name="approval_reference" required maxLength={160} /></div>
                    <div className="field"><label htmlFor={`sav-ap-hash-${product.id}`}>Approval evidence SHA-256</label><input id={`sav-ap-hash-${product.id}`} name="approval_evidence_hash" required minLength={64} maxLength={64} pattern="[A-Fa-f0-9]{64}" /></div>
                    <div className="field"><label htmlFor={`sav-ap-note-${product.id}`}>Independent review note</label><textarea id={`sav-ap-note-${product.id}`} name="approval_note" required minLength={10} maxLength={2000} rows={3} /></div>
                    <button className="button" type="submit">Approve and activate</button>
                  </form>
                ) : null}
              </div>
            ))}</div> : <StateNotice state="empty" message="No savings products have been configured." />}
          </section>

          <section className="panel">
            <h2>Protection product approvals</h2>
            {protectionProducts.length ? <div className="case-list">{protectionProducts.map((product) => (
              <div className="case-card" key={product.id}>
                <div className="case-card-head"><div><p className="eyebrow">{product.code}</p><h3>{product.name}</h3></div><span className={`badge ${product.status === "active" ? "ok" : "warn"}`}>{product.status}</span></div>
                <p className="muted">Insurer: {product.insurer_name} · author user {product.created_by ?? "unknown"} · approver {product.approved_by ?? "pending"}</p>
                {product.status !== "active" && product.status !== "retired" ? (
                  <form action={activateProtectionProductAction} className="form-grid" style={{ marginTop: 12 }}>
                    <input type="hidden" name="product_id" value={product.id} />
                    <div className="field"><label htmlFor={`prt-ap-ref-${product.id}`}>Approval reference</label><input id={`prt-ap-ref-${product.id}`} name="approval_reference" required maxLength={160} /></div>
                    <div className="field"><label htmlFor={`prt-ap-hash-${product.id}`}>Approval evidence SHA-256</label><input id={`prt-ap-hash-${product.id}`} name="approval_evidence_hash" required minLength={64} maxLength={64} pattern="[A-Fa-f0-9]{64}" /></div>
                    <div className="field"><label htmlFor={`prt-ap-note-${product.id}`}>Independent review note</label><textarea id={`prt-ap-note-${product.id}`} name="approval_note" required minLength={10} maxLength={2000} rows={3} /></div>
                    <button className="button" type="submit">Approve and activate</button>
                  </form>
                ) : null}
              </div>
            ))}</div> : <StateNotice state="empty" message="No protection products have been configured." />}
          </section>
        </div>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Control rule</h2>
          <p className="muted">Product authoring and activation are separate duties. Evidence hashes must correspond to controlled partner or approval evidence. Operators record external partner truth; they do not manufacture savings balances, insurance issuance or claim decisions inside OpFin.</p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load Save & Protection operations.";

    return (
      <Screen title="Save & Protection operations" description="Operate governed savings and protection workflows.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
