import {
  reconcileEssentialsAdvanceAction,
  reconcileEssentialsRepaymentAction,
  saveEssentialsBillerAction,
  saveEssentialsLenderAction,
  verifyEssentialsAccountAdminAction
} from "@/app/essentials-admin-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { essentialsApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

const notices: Record<string, string> = {
  "biller-saved": "Biller configuration saved.",
  "lender-saved": "Third-party lender configuration saved.",
  "verification-updated": "Account verification updated.",
  reconciled: "Provider fulfilment reconciliation completed.",
  "repayment-reconciled": "Repayment reconciliation completed."
};

export default async function AdminEssentialsPage({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string; status?: string }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const [portfolioResponse, queueResponse] = await Promise.all([
      essentialsApi.adminPortfolio(token),
      essentialsApi.adminWorkQueue(token)
    ]);
    const portfolio = portfolioResponse.data;
    const queue = queueResponse.data;

    return (
      <Screen
        title="Essentials operations"
        description="Operate billers, rental verification, third-party lender routes, funding capacity and purpose-bound fulfilment without making OpFin the primary lender."
      >
        {params?.status && notices[params.status] ? <StateNotice state="success" message={notices[params.status]} /> : null}
        {params?.message ? (
          <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} />
        ) : null}

        <div className="grid grid-4">
          <section className="panel"><p className="eyebrow">ACTIVE / EXCEPTION</p><div className="stat">{portfolio.active_advances}</div><p className="muted">Active, overdue or awaiting provider confirmation.</p></section>
          <section className="panel"><p className="eyebrow">PRINCIPAL OUTSTANDING</p><div className="stat">{formatUgx(portfolio.principal_outstanding_minor)}</div><p className="muted">Third-party lender principal still deployed.</p></section>
          <section className="panel"><p className="eyebrow">TOTAL OUTSTANDING</p><div className="stat">{formatUgx(portfolio.total_outstanding_minor)}</div><p className="muted">Customer obligation including contracted cost.</p></section>
          <section className="panel"><p className="eyebrow">VERIFICATION QUEUE</p><div className="stat">{portfolio.pending_account_verification}</div><p className="muted">Utility/service or rental accounts awaiting verification.</p></section>
        </div>

        <section className="panel">
          <h2>Verification queue</h2>
          {queue.pending_accounts.length === 0 ? (
            <StateNotice state="empty" message="No Essentials accounts require operations review." />
          ) : (
            <div className="case-list">
              {queue.pending_accounts.map((account) => {
                const metadata = account.metadata ?? {};
                return (
                  <article className="case-card" key={account.id}>
                    <div className="case-card-head">
                      <div>
                        <strong>{account.biller.name}</strong>
                        <p className="muted">
                          User {account.user_id} · {account.biller.category} · •••• {account.reference_last4}
                        </p>
                      </div>
                      <span className="badge warn">{account.verification_status.replaceAll("_", " ")}</span>
                    </div>
                    {account.biller.category === "rent" ? (
                      <div className="grid grid-3">
                        <div><strong>Landlord</strong><p>{String(metadata.landlord_name ?? "Not supplied")}</p></div>
                        <div><strong>Beneficiary</strong><p>{String(metadata.beneficiary_name ?? "Not supplied")}</p></div>
                        <div><strong>Channel</strong><p>{String(metadata.beneficiary_channel ?? "Not supplied")}</p></div>
                      </div>
                    ) : null}
                    <form action={verifyEssentialsAccountAdminAction} className="inline-form">
                      <input type="hidden" name="account_id" value={account.id} />
                      <input name="provider_reference" placeholder="Verification / beneficiary reference" />
                      <button className="button" name="status" value="verified" type="submit">Verify</button>
                      <button className="button secondary" name="status" value="failed" type="submit">Reject</button>
                    </form>
                  </article>
                );
              })}
            </div>
          )}
        </section>

        <section className="panel">
          <h2>Fulfilment & arrears exceptions</h2>
          {queue.exception_advances.length === 0 ? (
            <StateNotice state="empty" message="No Essentials advances require reconciliation attention." />
          ) : (
            <div className="case-list">
              {queue.exception_advances.map((advance) => (
                <article className="case-card" key={advance.id}>
                  <div className="case-card-head">
                    <div>
                      <strong>{advance.reference}</strong>
                      <p className="muted">Lender partner {advance.lender_partner_id} · Product {advance.partner_product_id}</p>
                    </div>
                    <span className="badge warn">{advance.status.replaceAll("_", " ")}</span>
                  </div>
                  <p>Principal {formatUgx(advance.principal_outstanding_minor)} · Total outstanding {formatUgx(advance.outstanding_minor)}</p>
                  {["funding_reserved", "lender_funding_pending", "lender_reversal_pending", "fulfilment_pending"].includes(advance.status) ? (
                    <form action={reconcileEssentialsAdvanceAction}>
                      <input type="hidden" name="advance_id" value={advance.id} />
                      <button className="button secondary" type="submit">Reconcile provider status</button>
                    </form>
                  ) : null}
                </article>
              ))}
            </div>
          )}
        </section>

        <section className="panel">
          <h2>Repayment confirmation queue</h2>
          {queue.pending_repayments.length === 0 ? (
            <StateNotice state="empty" message="No Essentials repayments are awaiting provider confirmation." />
          ) : (
            <div className="case-list">
              {queue.pending_repayments.map((repayment) => (
                <article className="case-card" key={repayment.id}>
                  <div className="case-card-head">
                    <div>
                      <strong>{repayment.reference}</strong>
                      <p className="muted">Advance {repayment.advance_id} · Customer {repayment.user_id}</p>
                    </div>
                    <span className="badge warn">{repayment.status.replaceAll("_", " ")}</span>
                  </div>
                  <p>{formatUgx(repayment.amount_minor)} awaiting confirmed collection finality.</p>
                  <form action={reconcileEssentialsRepaymentAction}>
                    <input type="hidden" name="repayment_id" value={repayment.id} />
                    <button className="button secondary" type="submit">Reconcile repayment</button>
                  </form>
                </article>
              ))}
            </div>
          )}
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Add or update a biller</h2>
            <form action={saveEssentialsBillerAction} className="form-grid">
              <div className="field"><label htmlFor="code">Code</label><input id="code" name="code" required placeholder="e.g. UEDCL" /></div>
              <div className="field"><label htmlFor="name">Name</label><input id="name" name="name" required /></div>
              <div className="field">
                <label htmlFor="category">Category</label>
                <select id="category" name="category" defaultValue="electricity" required>
                  <option value="electricity">Electricity</option>
                  <option value="water">Water</option>
                  <option value="internet">Internet</option>
                  <option value="television">Television</option>
                  <option value="energy">Energy / LPG</option>
                  <option value="rent">Rent</option>
                </select>
              </div>
              <div className="field"><label htmlFor="account_label">Account label</label><input id="account_label" name="account_label" required placeholder="Meter number" /></div>
              <div className="field">
                <label htmlFor="route">Verification / payment route</label>
                <select id="route" name="route" defaultValue="cpay" required>
                  <option value="cpay">CPay</option>
                  <option value="manual_verification">Manual verification</option>
                </select>
              </div>
              <button className="button" type="submit">Save biller</button>
            </form>
          </section>

          <section className="panel">
            <h2>Current billers</h2>
            <div className="case-list">
              {queue.billers.map((biller) => (
                <article className="case-card" key={biller.id}>
                  <div className="case-card-head"><strong>{biller.name}</strong><span className="badge">{biller.category}</span></div>
                  <p className="muted">{biller.code} · {biller.route} · {biller.account_label}</p>
                </article>
              ))}
            </div>
          </section>
        </div>

        <section className="panel">
          <h2>Configure a third-party Essentials lender</h2>
          <p className="muted">
            OpFin is intentionally blocked from being configured as the primary Essentials lender. Active lenders require licensing evidence and a funded mandate or a Cito-managed lender decision route.
          </p>
          <form action={saveEssentialsLenderAction} className="form-grid">
            <div className="grid grid-3">
              <div className="field"><label htmlFor="partner_code">Lender code</label><input id="partner_code" name="partner_code" required /></div>
              <div className="field"><label htmlFor="partner_name">Lender name</label><input id="partner_name" name="partner_name" required /></div>
              <div className="field">
                <label htmlFor="partner_type">Lender type</label>
                <select id="partner_type" name="partner_type" defaultValue="financial_institution">
                  <option value="bank">Bank</option><option value="mfi">MFI</option><option value="sacco">SACCO</option>
                  <option value="credit_provider">Credit provider</option><option value="financial_institution">Financial institution</option>
                </select>
              </div>
              <div className="field"><label htmlFor="licence_number">Licence number</label><input id="licence_number" name="licence_number" required /></div>
              <div className="field"><label htmlFor="licence_authority">Licensing authority</label><input id="licence_authority" name="licence_authority" required /></div>
              <div className="field"><label htmlFor="product_code">Product code</label><input id="product_code" name="product_code" required /></div>
              <div className="field"><label htmlFor="product_name">Product name</label><input id="product_name" name="product_name" required /></div>
              <div className="field">
                <label htmlFor="product_type">Product type</label>
                <select id="product_type" name="product_type" defaultValue="essentials_credit">
                  <option value="essentials_credit">Essentials credit</option><option value="utility_credit">Utility credit</option>
                  <option value="rent_credit">Rent credit</option><option value="sme_essentials_credit">SME Essentials credit</option>
                </select>
              </div>
              <div className="field"><label htmlFor="categories">Allowed categories</label><input id="categories" name="categories" placeholder="electricity,water,internet,rent" /></div>
              <div className="field"><label htmlFor="min_score">Minimum OpFin Score</label><input id="min_score" name="min_score" type="number" min="0" max="100" defaultValue="0" /></div>
              <div className="field"><label htmlFor="min_coverage_percent">Minimum profile coverage %</label><input id="min_coverage_percent" name="min_coverage_percent" type="number" min="0" max="100" defaultValue="0" /></div>
              <div className="field"><label htmlFor="min_limit_minor">Minimum limit (UGX)</label><input id="min_limit_minor" name="min_limit_minor" type="number" min="1" defaultValue="10000" /></div>
              <div className="field"><label htmlFor="max_limit_minor">Maximum limit (UGX)</label><input id="max_limit_minor" name="max_limit_minor" type="number" min="1" defaultValue="500000" /></div>
              <div className="field"><label htmlFor="line_valid_days">Line validity (days)</label><input id="line_valid_days" name="line_valid_days" type="number" min="1" max="365" defaultValue="30" /></div>
              <div className="field"><label htmlFor="term_days">Term (days)</label><input id="term_days" name="term_days" type="number" min="61" defaultValue="90" required /></div>
              <div className="field"><label htmlFor="monthly_interest_rate_percent">Monthly interest %</label><input id="monthly_interest_rate_percent" name="monthly_interest_rate_percent" type="number" min="0" step="0.01" defaultValue="0" /></div>
              <div className="field"><label htmlFor="fixed_fee_minor">Fixed customer fee (UGX)</label><input id="fixed_fee_minor" name="fixed_fee_minor" type="number" min="0" defaultValue="0" /></div>
              <div className="field"><label htmlFor="fee_percent">Customer fee %</label><input id="fee_percent" name="fee_percent" type="number" min="0" step="0.01" defaultValue="0" /></div>
              <div className="field"><label htmlFor="opfin_servicing_fee_minor">OpFin servicing amount (UGX)</label><input id="opfin_servicing_fee_minor" name="opfin_servicing_fee_minor" type="number" min="0" defaultValue="0" /></div>
              <div className="field"><label htmlFor="partner_commission_minor">Distribution commission (UGX)</label><input id="partner_commission_minor" name="partner_commission_minor" type="number" min="0" defaultValue="0" /></div>
              <div className="field">
                <label htmlFor="decision_route">Decision / funding route</label>
                <select id="decision_route" name="decision_route" defaultValue="capital_mandate">
                  <option value="capital_mandate">Approved capital mandate</option>
                  <option value="cito">Cito-managed lender route</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="funding_pool_id">Funding pool (required for capital mandate)</label>
                <select id="funding_pool_id" name="funding_pool_id" defaultValue="">
                  <option value="">No pool / Cito route</option>
                  {queue.funding_pools.map((pool) => (
                    <option value={pool.id} key={pool.id}>
                      {pool.name} · available {formatUgx(Math.max(0, pool.committed_capital_minor - pool.deployed_capital_minor - pool.reserved_capital_minor))}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <button className="button" type="submit">Save lender product</button>
          </form>
        </section>

        <section className="panel">
          <h2>Configured lender products</h2>
          {queue.lenders.length === 0 ? (
            <StateNotice state="empty" message="No third-party Essentials lender product is active yet." />
          ) : (
            <div className="case-list">
              {queue.lenders.map((lender) => (
                <article className="case-card" key={lender.product_id}>
                  <div className="case-card-head">
                    <div><strong>{lender.partner_name}</strong><p className="muted">{lender.product_name} · {lender.product_type}</p></div>
                    <span className="badge">{lender.product_status}</span>
                  </div>
                  <p className="muted">{lender.partner_code} · {lender.partner_type} · partner {lender.partner_status}</p>
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return (
      <Screen title="Essentials operations" description="Operate third-party lender-backed essential-service financing.">
        <StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load Essentials operations."} />
      </Screen>
    );
  }
}
