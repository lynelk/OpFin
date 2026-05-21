import { Screen } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";

export default async function LoanApplyPage() {
  const products = await opfinApi.products();

  return (
    <Screen title="Loan application flow" description="Mock-first application form using only known product and loan application fields.">
      <section className="panel">
        <h2>Application details</h2>
        <form className="form-grid">
          <div className="field">
            <label htmlFor="product">Loan product</label>
            <select id="product" name="loan_product_id" defaultValue={products.data[0]?.id}>
              {products.data.map((product) => (
                <option key={product.id} value={product.id}>{product.name}</option>
              ))}
            </select>
          </div>
          <div className="field">
            <label htmlFor="amount">Amount</label>
            <input id="amount" name="amount" inputMode="numeric" defaultValue="100000" />
          </div>
          <div className="field">
            <label htmlFor="reason">Reason</label>
            <textarea id="reason" name="reason" rows={4} defaultValue="School fees" />
          </div>
          <button className="button" type="button">Preview decision</button>
        </form>
      </section>
    </Screen>
  );
}
