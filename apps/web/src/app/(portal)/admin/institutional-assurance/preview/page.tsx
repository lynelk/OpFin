import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { assuranceRequest } from "@/lib/api/institutional-assurance";

type Params = {
  contract_form?: string; currency?: string; principal_amount_minor?: string;
  cash_disbursement_minor?: string; opfin_share_bps?: string;
};
type Journal = {
  illustration: string;
  debit: { code: string; amount_minor: number };
  credit: { code: string; amount_minor: number };
};
type Preview = {
  status: string; reason: string; mode: string; currency: string;
  principal_amount_minor: number; cash_disbursement_minor: number;
  illustrative_opfin_principal_minor?: number;
  illustrative_partner_principal_minor?: number;
  release_approved: boolean; shared_origination_enabled: boolean;
  ledger_posted: boolean; journals: Journal[]; outstanding_work: string[];
};
export default async function SharedAccountingPreviewPage({ searchParams }: { searchParams?: Promise<Params> }) {
  const params = await searchParams;
  const inputGiven = Boolean(params?.contract_form);
  let preview: Preview | null = null;
  let error = "";
  if (inputGiven) {
    try {
      const amounts = [params?.principal_amount_minor, params?.cash_disbursement_minor, params?.opfin_share_bps]
        .map(value => Number(value));
      if (amounts.some(value => !Number.isSafeInteger(value) || value < 1)) throw new Error("Use positive whole minor units and basis points.");
      preview = await assuranceRequest<Preview>("/shared-accounting-preview", await getAccessToken(), {
        contract_form: params?.contract_form, currency: params?.currency?.toUpperCase(),
        principal_amount_minor: amounts[0], cash_disbursement_minor: amounts[1],
        opfin_share_bps: amounts[2],
      });
    } catch (reason) {
      error = reason instanceof Error ? reason.message : "Unable to model the synthetic shared-credit scenario.";
    }
  }
  const money = (amount: number) => amount.toLocaleString("en-GB");
  return <Screen title="Provisional shared-credit accounting" description="Synthetic journal illustrations only. Documentary evidence is temporarily deferred in non-production environments, not approved or waived for live lending.">
    <section className="panel">
      <p><Link href="/admin/institutional-assurance">Back to institution reconciliation and finance assurance</Link></p>
      <p>No real loan, ledger posting, payment-provider instruction, institutional ownership assertion or professional sign-off is created by this page.</p>
      <form method="get" className="form-grid">
        <label className="field">Assumed legal arrangement<select name="contract_form" defaultValue={params?.contract_form ?? "direct_joint_creditor"} required>
          <option value="direct_joint_creditor">Direct joint creditor: separate legal loan rights (hypothetical)</option>
          <option value="funded_participation">Funded participation</option>
          <option value="financial_guarantee">Financial guarantee</option>
          <option value="first_loss">First-loss risk arrangement</option>
        </select></label>
        <label className="field">Currency<input name="currency" defaultValue={params?.currency ?? "UGX"} required /></label>
        <label className="field">Gross principal in minor units<input name="principal_amount_minor" type="number" min="1" defaultValue={params?.principal_amount_minor ?? "100000"} required /></label>
        <label className="field">Borrower cash disbursement in minor units<input name="cash_disbursement_minor" type="number" min="1" defaultValue={params?.cash_disbursement_minor ?? "100000"} required /></label>
        <label className="field">Hypothetical OpFin share (basis points)<input name="opfin_share_bps" type="number" min="1" max="9999" defaultValue={params?.opfin_share_bps ?? "4000"} required /></label>
        <button className="button">Calculate synthetic case</button>
      </form>
    </section>
    {error ? <StateNotice state="validation" message={error} /> : null}
    {preview && <section className="panel">
      <h2>Illustration: {preview.status.replaceAll("_", " ")}</h2>
      <p>{preview.reason}</p>
      <p><strong>Posting: none.</strong> Shared origination: blocked. Reviewer certification: absent.</p>
      {preview.illustrative_opfin_principal_minor !== undefined && <p>
        Hypothetical OpFin share: {preview.currency} {money(preview.illustrative_opfin_principal_minor)};
        external lender share: {preview.currency} {money(preview.illustrative_partner_principal_minor ?? 0)}.
      </p>}
      {preview.journals.length > 0 && <table className="table"><thead>
        <tr><th>Separate settlement leg</th><th>Debit</th><th>Credit</th></tr>
      </thead><tbody>
        {preview.journals.map(journal => <tr key={journal.illustration}>
          <td>{journal.illustration.replaceAll("_", " ")}</td>
          <td>{journal.debit.code}: {money(journal.debit.amount_minor)}</td>
          <td>{journal.credit.code}: {money(journal.credit.amount_minor)}</td>
        </tr>)}
      </tbody></table>}
      <h3>Still required before production journal implementation</h3>
      <p>{preview.outstanding_work.map(item => item.replaceAll("_", " ")).join("; ")}.</p>
    </section>}
  </Screen>;
}
