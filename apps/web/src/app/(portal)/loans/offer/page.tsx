import Link from "next/link";
import { acceptCreditOfferAction } from "@/app/actions";
import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function LoanOfferPage({ searchParams }: { searchParams?: Promise<{ offer?: string; status?: string; error?: string; message?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();
  const offerId = Number(params?.offer);

  if (!Number.isInteger(offerId) || offerId <= 0) {
    return (
      <Screen title="Credit offer" description="Review a formal offer before any money is moved.">
        <StateNotice state="empty" message="No credit offer was selected." />
        <Link className="button" href="/borrow">Back to Borrow</Link>
      </Screen>
    );
  }

  try {
    const response = await opfinApi.creditOffer(offerId, token);
    const offer = response.data.offer;
    const disclosureHash = response.data.disclosure_hash;
    const lender = offer.disclosure_snapshot?.lender_of_record as { legal_name?: string; authority_reference?: string; regulator?: string } | undefined;
    const canAccept = offer.status === "offered";

    return (
      <Screen
        title="Credit offer"
        description="This is the versioned offer that will govern acceptance. Review every cost and the amount you will actually receive."
        action={<Link className="button secondary" href="/borrow">Back to Borrow</Link>}
      >
        {params?.message ? <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} /> : null}
        {params?.status === "disbursement_pending" ? (
          <StateNotice state="success" message="Your offer was accepted. Disbursement is pending confirmation from the payment rail; OpFin will not activate the loan until success is confirmed." />
        ) : null}

        <section className="panel">
          <div className="split-heading">
            <div>
              <p className="eyebrow">Offer {offer.offer_reference}</p>
              <h2>{offer.status}</h2>
            </div>
            <span className="badge">Version {offer.version}</span>
          </div>

          <p><strong>Lender:</strong> {lender?.legal_name ?? "See the lender details in your offer disclosures."}</p>
          {lender?.authority_reference ? <p>{lender.regulator ?? "Authority"}: {lender.authority_reference}</p> : null}
          <p className="muted">OpFin provides the platform and orchestration. Credit is provided by the named lender.</p>
          <table className="table">
            <tbody>
              <tr><th>Approved principal</th><td>{formatUgx(offer.principal_amount_minor)}</td></tr>
              <tr><th>Interest</th><td>{formatUgx(offer.interest_amount_minor)}</td></tr>
              <tr><th>Fees</th><td>{formatUgx(offer.fees_minor)}</td></tr>
              {offer.disclosure_snapshot?.fee_breakdown ? <tr><th>How fees apply</th><td>{String((offer.disclosure_snapshot.fee_breakdown as { treatment?: string }).treatment ?? offer.fee_treatment)}</td></tr> : null}
              {offer.disclosure_snapshot?.total_cost_of_credit_minor != null ? <tr><th>Total cost of credit</th><td>{formatUgx(Number(offer.disclosure_snapshot.total_cost_of_credit_minor))}</td></tr> : null}
              <tr><th>Amount you receive</th><td><strong>{formatUgx(offer.net_disbursement_minor)}</strong></td></tr>
              <tr><th>Total repayment</th><td><strong>{formatUgx(offer.total_repayment_minor)}</strong></td></tr>
              <tr><th>Duration</th><td>{offer.duration_days} days</td></tr>
              <tr><th>Rate basis</th><td>{offer.interest_rate_percent}% {offer.interest_cycle}, {offer.interest_type}</td></tr>
              <tr><th>Repayment frequency</th><td>{offer.repayment_frequency}</td></tr>
              <tr><th>Fee treatment</th><td>{offer.fee_treatment}</td></tr>
              <tr><th>Offer expires</th><td>{new Date(offer.expires_at).toLocaleString()}</td></tr>
              <tr><th>Decision policy</th><td>{offer.policy_version}</td></tr>
            </tbody>
          </table>
          {offer.disclosure_snapshot?.interest_calculation ? <p className="muted"><strong>How interest is calculated:</strong> {String(offer.disclosure_snapshot.interest_calculation)}</p> : null}
          {offer.disclosure_snapshot?.default_and_penalty_terms ? (
            <div className="notice">
              <strong>If you do not pay on time:</strong>{" "}
              {String((offer.disclosure_snapshot.default_and_penalty_terms as { cap_basis?: string }).cap_basis ?? "Regulatory default-interest and recovery limits apply.")}
            </div>
          ) : null}
          {offer.disclosure_snapshot?.complaints_procedure ? (
            <div className="notice">
              <strong>Complaints:</strong> OpFin records complaints and targets resolution within{" "}
              {String((offer.disclosure_snapshot.complaints_procedure as { resolution_target_days?: number }).resolution_target_days ?? 30)} days.
            </div>
          ) : null}
        </section>

        {canAccept ? (
          <section className="panel">
            <h2>Acceptance</h2>
            <p className="muted">
              Accepting authorizes OpFin to initiate the disclosed net disbursement through CPay. A loan becomes active only after the payout is confirmed successful.
            </p>
            <form action={acceptCreditOfferAction} className="form-grid">
              <input type="hidden" name="offer_id" value={offer.id} />
              <input type="hidden" name="disclosure_hash" value={disclosureHash} />
              <label className="consent-check">
                <input type="checkbox" name="credit_reporting_consent" required />
                <span>I consent to complete and accurate positive or negative credit information about this loan being reported to the applicable authorised credit-reference mechanism.</span>
              </label>
              <label className="consent-check">
                <input type="checkbox" name="accept_disclosures" required />
                <span>I have reviewed and accept this exact offer, including the interest calculation, fees and when they apply, default terms, total cost, repayment dates and complaints procedure.</span>
              </label>
              <button className="button" type="submit">Accept offer and request disbursement</button>
            </form>
          </section>
        ) : offer.status === "disbursement_pending" ? (
          <StateNotice state="empty" message="Disbursement is awaiting payment confirmation. Repeated acceptance will not create a second payout instruction." />
        ) : offer.status === "disbursed" ? (
          <StateNotice state="success" message="Disbursement is confirmed and the loan has been activated from this accepted offer." />
        ) : offer.status === "expired" ? (
          <StateNotice state="validation" message="This offer has expired and cannot be accepted. A new version must be generated after the required review." />
        ) : (
          <StateNotice state="empty" message={`This offer cannot be accepted while its status is ${offer.status}.`} />
        )}
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load the credit offer.";

    return (
      <Screen title="Credit offer" description="Review a formal offer before any money is moved.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
