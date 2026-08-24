import { submitLoanApplicationAction } from "@/app/actions";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function LoanApplyPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const [products, institutions] = await Promise.all([
      opfinApi.products(token),
      opfinApi.institutions(token)
    ]);
    const selectedProduct = products.data[0];
    const terms = selectedProduct ? await opfinApi.productTerms(selectedProduct.id, token) : null;
    const selectedTerm = terms?.data[0];
    const selectedInstitution = selectedProduct?.institution ?? institutions.data[0];

    return (
      <Screen title="Credit application" description="Tell OpFin what you need. Submission starts assessment only; it does not approve or disburse a loan.">
        <section className="panel">
          <h2>Application details</h2>
          {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}
          {!selectedProduct || !selectedTerm || !selectedInstitution ? (
            <StateNotice state="empty" message="No active credit products or terms are available." />
          ) : (
            <form action={submitLoanApplicationAction} className="form-grid">
              <div className="field">
                <label htmlFor="product">Credit product</label>
                <select id="product" name="loan_product_id" defaultValue={selectedProduct.id}>
                  {products.data.map((product) => (
                    <option key={product.id} value={product.id}>{product.name}</option>
                  ))}
                </select>
              </div>
              <input type="hidden" name="loan_product_term_id" value={selectedTerm.id} />
              <div className="field">
                <label htmlFor="institution">Institution</label>
                <select id="institution" name="institution_id" defaultValue={selectedInstitution.id}>
                  {institutions.data.map((institution) => (
                    <option key={institution.id} value={institution.id}>{institution.name}</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="term">Requested term</label>
                <input id="term" value={`${selectedTerm.duration} days, ${selectedTerm.repayment_frequency} repayment`} readOnly />
              </div>
              <div className="field">
                <label htmlFor="amount">Requested amount (UGX)</label>
                <input id="amount" name="amount" inputMode="numeric" min="1" defaultValue="100000" required />
              </div>
              <div className="field">
                <label htmlFor="reason">Reason</label>
                <textarea id="reason" name="reason" rows={4} defaultValue="School fees" required />
              </div>
              <div className="placeholder">
                Final interest, fees, amount received, total repayment and expiry will be shown only in a versioned offer after assessment. You can review that offer before accepting anything.
              </div>
              <button className="button" type="submit">Submit for assessment</button>
            </form>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load credit application data.";

    return (
      <Screen title="Credit application" description="Tell OpFin what you need. Submission starts assessment only; it does not approve or disburse a loan.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
