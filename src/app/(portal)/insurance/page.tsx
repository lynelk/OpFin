import Link from "next/link";
import { enrollProtectionAction } from "@/app/save-protection-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { saveProtectionApi } from "@/lib/api/save-protection";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function InsurancePage({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const [productsResponse, policiesResponse] = await Promise.all([
      saveProtectionApi.protectionProducts("UG", token),
      saveProtectionApi.protectionPolicies(token)
    ]);
    const products = productsResponse.data.products;
    const policies = policiesResponse.data.policies;

    return (
      <Screen
        title="Protection"
        description="Compare approved products, identify the insurer or underwriter, accept the exact disclosure, and track premium collection separately from actual policy issuance."
        action={<Link className="button secondary" href="/save">Back to Save & Protect</Link>}
      >
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}
        <StateNotice state="success" message={productsResponse.data.risk_notice} />

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Your protection</h2>
          {policies.length ? (
            <div className="grid grid-2">
              {policies.map((policy) => (
                <div className="case-card" key={policy.id}>
                  <div className="case-card-head">
                    <div>
                      <p className="eyebrow">{policy.product.name}</p>
                      <h3>{policy.external_policy_number ?? policy.policy_reference}</h3>
                    </div>
                    <span className={`badge ${policy.status === "active" ? "ok" : "warn"}`}>{policy.status}</span>
                  </div>
                  <table className="table">
                    <tbody>
                      <tr><th>Insurer</th><td>{policy.product.insurer_name}</td></tr>
                      <tr><th>Underwriter</th><td>{policy.product.underwriter_name ?? "Insurer as disclosed"}</td></tr>
                      <tr><th>Premium</th><td>{formatUgx(policy.premium_amount_minor)} · {policy.premium_frequency}</td></tr>
                      <tr><th>Cover limit</th><td>{policy.coverage_limit_minor ? formatUgx(policy.coverage_limit_minor) : "See product terms"}</td></tr>
                      <tr><th>Cover period</th><td>{policy.cover_start_date && policy.cover_end_date ? `${policy.cover_start_date} to ${policy.cover_end_date}` : "Awaiting insurer issuance"}</td></tr>
                    </tbody>
                  </table>
                  <Link className="button secondary" href={`/insurance/${policy.id}`}>Open policy</Link>
                </div>
              ))}
            </div>
          ) : (
            <StateNotice state="empty" message="You do not have a protection policy record yet. Review the approved catalogue below." />
          )}
        </section>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>Approved protection products</h2>
          {products.length ? (
            <div className="grid grid-2">
              {products.map((product) => (
                <div className="case-card" key={product.id}>
                  <div className="case-card-head">
                    <div>
                      <p className="eyebrow">{product.product_type}</p>
                      <h3>{product.name}</h3>
                    </div>
                    <span className="badge ok">approved</span>
                  </div>
                  <table className="table">
                    <tbody>
                      <tr><th>Insurer</th><td>{product.insurer_name}</td></tr>
                      <tr><th>Underwriter</th><td>{product.underwriter_name ?? "As disclosed by insurer"}</td></tr>
                      <tr><th>Premium</th><td>{formatUgx(product.premium_amount_minor)} · {product.premium_frequency}</td></tr>
                      <tr><th>Cover limit</th><td>{product.coverage_limit_minor ? formatUgx(product.coverage_limit_minor) : "See terms"}</td></tr>
                      <tr><th>Disclosure version</th><td>{product.disclosure_version}</td></tr>
                    </tbody>
                  </table>

                  {product.benefits?.length ? (
                    <div>
                      <p className="eyebrow">Benefits</p>
                      <ul>{product.benefits.map((benefit) => <li key={benefit}>{benefit}</li>)}</ul>
                    </div>
                  ) : null}
                  <div>
                    <p className="eyebrow">Exclusions</p>
                    {product.exclusions?.length ? <ul>{product.exclusions.map((exclusion) => <li key={exclusion}>{exclusion}</li>)}</ul> : <p className="muted">Review the controlled terms for all exclusions.</p>}
                  </div>
                  {product.terms_url ? <a className="button secondary" href={product.terms_url}>Read controlled terms</a> : null}

                  <form action={enrollProtectionAction} className="form-grid" style={{ marginTop: 16 }}>
                    <input type="hidden" name="product_id" value={product.id} />
                    <input type="hidden" name="disclosure_hash" value={product.disclosure_hash} />
                    <label className="consent-check">
                      <input type="checkbox" name="accept_disclosures" required />
                      <span>
                        I have reviewed this exact product disclosure and understand that {product.insurer_name}{product.underwriter_name ? ` / ${product.underwriter_name}` : ""} issues and manages the cover and claim decisions. Paying through OpFin does not by itself activate cover.
                      </span>
                    </label>
                    <button className="button" type="submit">Accept disclosure and enroll</button>
                  </form>
                </div>
              ))}
            </div>
          ) : (
            <StateNotice state="empty" message="No independently approved protection product is currently published for your country." />
          )}
        </section>

        <section className="panel" style={{ marginTop: 16 }}>
          <h2>How activation works</h2>
          <p className="muted">
            Enrollment records your acceptance of a versioned disclosure. CPay can then initiate premium collection. A successful collection remains pending until insurer settlement evidence is recorded, and cover is not shown as active until the insurer or underwriter provides policy issuance details and the cover period.
          </p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load protection.";

    return (
      <Screen title="Protection" description="Review insurer-issued protection products and policies.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
