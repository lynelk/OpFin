import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { OpfinApiError } from "@/lib/api/errors";
import { opfinApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function LoanDecisionPage({ searchParams }: { searchParams?: Promise<{ application?: string; status?: string }> }) {
  const params = await searchParams;
  const token = await getAccessToken();
  const applicationId = Number(params?.application);

  if (!Number.isInteger(applicationId) || applicationId <= 0) {
    return (
      <Screen title="Credit assessment" description="Track the status of a submitted credit application.">
        <StateNotice state="empty" message="No credit application was selected." />
        <Link className="button" href="/borrow">Back to Borrow</Link>
      </Screen>
    );
  }

  try {
    const response = await opfinApi.creditApplication(applicationId, token);
    const application = response.data.application;
    const decision = application.credit_decision;
    const offers = application.credit_offers ?? [];
    const currentOffer = [...offers].sort((a, b) => b.version - a.version)[0];

    return (
      <Screen
        title="Credit assessment"
        description="Your application, decision and offer are separate controlled steps. Funds are not moved until you accept a valid offer."
        action={currentOffer ? <Link className="button" href={`/loans/offer?offer=${currentOffer.id}`}>Review offer</Link> : undefined}
      >
        {params?.status === "submitted" ? (
          <StateNotice state="success" message="Your application was submitted for assessment. No disbursement has been initiated." />
        ) : null}

        <div className="grid grid-2">
          <section className="panel">
            <p className="eyebrow">Application</p>
            <h2>{application.status}</h2>
            <table className="table">
              <tbody>
                <tr><th>Requested amount</th><td>{formatUgx(Number(application.amount))}</td></tr>
                <tr><th>Reason</th><td>{application.reason ?? "Not supplied"}</td></tr>
              </tbody>
            </table>
          </section>

          <section className="panel">
            <p className="eyebrow">Decision</p>
            {decision ? (
              <>
                <h2>{decision.status}</h2>
                <p className="muted">{decision.decision_summary}</p>
                {decision.status === "approved" ? (
                  <table className="table">
                    <tbody>
                      <tr><th>Approved amount</th><td>{formatUgx(decision.approved_amount_minor)}</td></tr>
                      <tr><th>Policy version</th><td>{decision.policy_version ?? "Recorded by operations"}</td></tr>
                    </tbody>
                  </table>
                ) : null}
                <div className="chip-row">
                  {decision.reason_codes.map((code) => <span className="badge warn" key={code}>{code}</span>)}
                </div>
              </>
            ) : (
              <StateNotice state="empty" message="Assessment is still in progress. Pricing and repayment terms are not final until a formal offer is generated." />
            )}
          </section>
        </div>

        <section className="panel">
          <h2>What happens next</h2>
          {currentOffer ? (
            <p className="muted">A versioned offer is ready. Review the amount you will receive, interest, fees, total repayment, expiry and repayment terms before accepting it.</p>
          ) : decision?.status === "declined" ? (
            <p className="muted">No funds will be moved. Review the decision reasons and contact support if information used in the assessment needs correction.</p>
          ) : (
            <p className="muted">OpFin will keep this application in assessment until the required decision controls are complete. An approved decision still does not move money by itself.</p>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load credit assessment state.";

    return (
      <Screen title="Credit assessment" description="Track the status of a submitted credit application.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
