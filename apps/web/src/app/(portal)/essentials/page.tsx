import {
  acceptEssentialsQuoteAction,
  addEssentialsAccountAction,
  authoriseEssentialsPartnerAction,
  createEssentialsQuoteAction,
  refreshEssentialsEligibilityAction,
  repayEssentialsAction,
  revokeEssentialsPartnerAction,
  verifyEssentialsAccountAction
} from "@/app/essentials-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { essentialsApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

const statusMessages: Record<string, string> = {
  "eligibility-refreshed": "Participating lender eligibility has been refreshed.",
  "account-saved": "Your essential-service account has been saved.",
  "verification-updated": "The service-account verification status has been updated.",
  "quote-created": "A lender-backed Essentials offer has been created. Review it below before accepting.",
  "finance-accepted": "Purpose-bound provider payment has been initiated.",
  "repayment-submitted": "Your repayment has been submitted for provider confirmation.",
  "platform-access-revoked": "The connected platform permission has been revoked.",
  "platform-access-granted": "The approved platform can now use the authorised Essentials actions for 30 days."
};

export default async function EssentialsPage({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string; status?: string }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const [summaryResponse, catalogueResponse, quotesResponse, authResponse] = await Promise.all([
      essentialsApi.summary(token),
      essentialsApi.catalogue(token),
      essentialsApi.quotes(token),
      essentialsApi.partnerAuthorisations(token)
    ]);

    const summary = summaryResponse.data;
    const billers = catalogueResponse.data.billers;
    const platforms = catalogueResponse.data.platforms ?? [];
    const quotes = quotesResponse.data.quotes;
    const authorisations = authResponse.data.authorisations;

    return (
      <Screen
        title="Essentials"
        description="Keep verified household and business essentials running through approved third-party lenders without turning OpFin into a lending-only product."
      >
        {params?.status && statusMessages[params.status] ? (
          <StateNotice state="success" message={statusMessages[params.status]} />
        ) : null}
        {params?.message ? (
          <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} />
        ) : null}

        <div className="grid grid-3">
          <section className="panel">
            <p className="eyebrow">AVAILABLE ESSENTIALS HEADROOM</p>
            <div className="stat">{formatUgx(summary.overall_available_limit_minor)}</div>
            <p className="muted">
              This is constrained by your overall OpFin responsible-credit position. Lender limits are not stacked together.
            </p>
          </section>
          <section className="panel">
            <p className="eyebrow">OUTSTANDING</p>
            <div className="stat">{formatUgx(summary.outstanding_minor)}</div>
            <p className="muted">Purpose-bound Essentials financing currently outstanding.</p>
          </section>
          <section className="panel">
            <p className="eyebrow">OPFIN&apos;S ROLE</p>
            <div className="stat stat-text">Arrange &amp; service</div>
            <p className="muted">The named third party in each offer is the lender. OpFin is not the primary lender.</p>
          </section>
        </div>

        <section className="panel">
          <div className="case-card-head">
            <div>
              <h2>Check participating lenders</h2>
              <p className="muted">
                OpFin uses your existing credit profile and consented data to check active third-party lender routes.
              </p>
            </div>
            <form action={refreshEssentialsEligibilityAction}>
              <button className="button" type="submit">Refresh eligibility</button>
            </form>
          </div>
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Add an essential service</h2>
            <form action={addEssentialsAccountAction} className="form-grid">
              <div className="field">
                <label htmlFor="biller_id">Service provider</label>
                <select id="biller_id" name="biller_id" required defaultValue="">
                  <option value="" disabled>Choose a provider</option>
                  {billers.map((biller) => (
                    <option key={biller.id} value={biller.id}>
                      {biller.name} · {biller.category}
                    </option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="account_reference">Account / meter / beneficiary reference</label>
                <input id="account_reference" name="account_reference" required autoComplete="off" />
              </div>
              <div className="field">
                <label htmlFor="nickname">Nickname</label>
                <input id="nickname" name="nickname" placeholder="e.g. Home electricity" />
              </div>
              <details>
                <summary>Rental beneficiary details</summary>
                <p className="muted">Required only when you select Rent. Rental beneficiaries are manually verified before finance can be offered.</p>
                <div className="field">
                  <label htmlFor="landlord_name">Landlord / property manager</label>
                  <input id="landlord_name" name="landlord_name" />
                </div>
                <div className="field">
                  <label htmlFor="beneficiary_name">Payment beneficiary</label>
                  <input id="beneficiary_name" name="beneficiary_name" />
                </div>
                <div className="field">
                  <label htmlFor="beneficiary_channel">Beneficiary payment channel</label>
                  <input id="beneficiary_channel" name="beneficiary_channel" placeholder="Mobile money or bank" />
                </div>
              </details>
              <button className="button" type="submit">Save essential</button>
            </form>
          </section>

          <section className="panel">
            <h2>How Essentials works</h2>
            <ol>
              <li>Save and verify the provider, account or rental beneficiary.</li>
              <li>OpFin checks approved third-party lender capacity within your overall responsible-credit headroom.</li>
              <li>Review the named lender, full cost and repayment schedule.</li>
              <li>You accept the offer. CPay pays the verified provider or beneficiary directly.</li>
              <li>Repay through OpFin; confirmed principal repayment restores lender capacity.</li>
            </ol>
            <p className="muted">No utility or rental advance is paid as cash to your wallet.</p>
          </section>
        </div>

        <section className="panel">
          <h2>My essentials</h2>
          {summary.accounts.length === 0 ? (
            <StateNotice state="empty" message="No essential-service accounts are saved yet." />
          ) : (
            <div className="case-list">
              {summary.accounts.map((account) => (
                <article className="case-card" key={account.id}>
                  <div className="case-card-head">
                    <div>
                      <strong>{account.nickname || account.biller.name}</strong>
                      <p className="muted">{account.biller.category} · •••• {account.reference_last4}</p>
                    </div>
                    <span className={"badge " + (account.verification_status === "verified" ? "ok" : "warn")}>
                      {account.verification_status.replaceAll("_", " ")}
                    </span>
                  </div>
                  <div className="quick-actions">
                    {account.verification_status === "pending" ? (
                      <form action={verifyEssentialsAccountAction}>
                        <input type="hidden" name="account_id" value={account.id} />
                        <button className="button secondary" type="submit">Verify account</button>
                      </form>
                    ) : null}
                    {account.verification_status === "verified" ? (
                      <form action={createEssentialsQuoteAction} className="inline-form">
                        <input type="hidden" name="account_id" value={account.id} />
                        <label htmlFor={"amount-" + account.id}>UGX</label>
                        <input id={"amount-" + account.id} name="amount_minor" type="number" min="1" step="1" required placeholder="Amount" />
                        <button className="button" type="submit">Check lender offer</button>
                      </form>
                    ) : null}
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>

        <section className="panel" id="offers">
          <h2>Lender offers</h2>
          {quotes.length === 0 ? (
            <StateNotice state="empty" message="No Essentials offers have been created yet." />
          ) : (
            <div className="case-list">
              {quotes.map((quote) => {
                const lender = quote.disclosure_snapshot?.lender?.name ?? "Third-party lender";
                return (
                  <article className="case-card" key={quote.id}>
                    <div className="case-card-head">
                      <div>
                        <p className="eyebrow">{quote.purpose_category.toUpperCase()}</p>
                        <h3>{lender}</h3>
                      </div>
                      <span className={"badge " + (quote.status === "offered" ? "warn" : "ok")}>{quote.status}</span>
                    </div>
                    <div className="grid grid-3">
                      <div><strong>Provider payment</strong><p>{formatUgx(quote.amount_minor)}</p></div>
                      <div><strong>Total repayment</strong><p>{formatUgx(quote.total_repayment_minor)}</p></div>
                      <div><strong>Term</strong><p>{quote.term_days} days</p></div>
                    </div>
                    <p className="muted">
                      Interest {formatUgx(quote.interest_minor)} · Fees {formatUgx(quote.fees_minor)} · OpFin arranges and services; {lender} is the lender.
                    </p>
                    {quote.status === "offered" ? (
                      <form action={acceptEssentialsQuoteAction} className="form-grid">
                        <input type="hidden" name="quote_id" value={quote.id} />
                        <input type="hidden" name="disclosure_hash" value={quote.disclosure_hash ?? ""} />
                        <label>
                          <input type="checkbox" name="confirm_terms" value="yes" required />{" "}
                          I have reviewed the lender, provider payment, total cost and repayment term.
                        </label>
                        <button className="button" type="submit">Accept &amp; pay provider</button>
                      </form>
                    ) : null}
                  </article>
                );
              })}
            </div>
          )}
        </section>

        <section className="panel">
          <h2>Financing activity</h2>
          {summary.advances.length === 0 ? (
            <StateNotice state="empty" message="No Essentials financing is active." />
          ) : (
            <div className="case-list">
              {summary.advances.map((advance) => (
                <article className="case-card" key={advance.id}>
                  <div className="case-card-head">
                    <strong>{advance.status.replaceAll("_", " ")}</strong>
                    <span className="badge">{advance.reference.slice(0, 8)}</span>
                  </div>
                  <p>Outstanding {formatUgx(advance.outstanding_minor)} · Principal {formatUgx(advance.principal_outstanding_minor)}</p>
                  {advance.final_due_date ? <p className="muted">Final due {new Date(advance.final_due_date).toLocaleDateString("en-UG")}</p> : null}
                  {["active", "overdue"].includes(advance.status) ? (
                    <form action={repayEssentialsAction} className="inline-form">
                      <input type="hidden" name="advance_id" value={advance.id} />
                      <input name="amount_minor" type="number" min="1" max={advance.outstanding_minor} defaultValue={advance.outstanding_minor} required />
                      <button className="button secondary" type="submit">Repay</button>
                    </form>
                  ) : null}
                </article>
              ))}
            </div>
          )}
        </section>

        <section className="panel">
          <div className="case-card-head">
            <div>
              <h2>Connected platforms</h2>
              <p className="muted">Stolets or another approved platform can use Essentials only after you grant the relevant Financial Space permissions. You can revoke access here.</p>
            </div>
            {platforms.length > 0 ? (
              <form action={authoriseEssentialsPartnerAction} className="inline-form">
                <select name="partner_account_id" defaultValue="" required>
                  <option value="" disabled>Choose platform</option>
                  {platforms.map((platform) => (
                    <option value={platform.id} key={platform.id}>{platform.name}</option>
                  ))}
                </select>
                <button className="button secondary" type="submit">Grant 30-day access</button>
              </form>
            ) : null}
          </div>
          <p className="muted">
            Stolets or another approved platform can use Essentials only after you grant the relevant Financial Space permissions. You can revoke access here.
          </p>
          {authorisations.length === 0 ? (
            <StateNotice state="empty" message="No external platform currently has Essentials permission." />
          ) : (
            <div className="case-list">
              {authorisations.map((authorisation) => (
                <article className="case-card" key={authorisation.id}>
                  <div className="case-card-head">
                    <div>
                      <strong>{authorisation.partner_name ?? "Approved platform"}</strong>
                      <p className="muted">{authorisation.scopes.join(", ")}</p>
                    </div>
                    <span className={"badge " + (authorisation.status === "active" ? "ok" : "")}>{authorisation.status}</span>
                  </div>
                  {authorisation.status === "active" ? (
                    <form action={revokeEssentialsPartnerAction}>
                      <input type="hidden" name="authorisation_id" value={authorisation.id} />
                      <button className="button secondary" type="submit">Revoke access</button>
                    </form>
                  ) : null}
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load OpFin Essentials.";
    return (
      <Screen title="Essentials" description="Purpose-bound access to verified essential services through approved third-party lenders.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
