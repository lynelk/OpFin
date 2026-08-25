import { JourneyCard } from "@/components/JourneyCard";
import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function BorrowPage() {
  const token = await getAccessToken();

  try {
    const [applicationsResult, offersResult] = await Promise.allSettled([
      opfinApi.creditApplications(token),
      opfinApi.creditOffers(token)
    ]);
    const applications = applicationsResult.status === "fulfilled" ? applicationsResult.value.data.applications : [];
    const offers = offersResult.status === "fulfilled" ? offersResult.value.data.offers : [];
    const latestApplication = applications[0];
    const actionableOffer = offers.find((offer) => offer.status === "offered") ?? offers[0];

    return (
      <Screen
        title="Borrow"
        description="Apply, follow assessment, review a formal offer and track disbursement without confusing any of those steps with each other."
      >
        <div className="grid grid-3">
          <JourneyCard
            title="Check what fits"
            description="Choose a product, requested amount and term. Submission starts assessment and never triggers a payout by itself."
            href="/loans/apply"
            action="Start application"
            status="pilot"
          />
          <JourneyCard
            title="Application status"
            description={latestApplication ? `Latest application: ${latestApplication.status}.` : "No current credit application."}
            href={latestApplication ? `/loans/decision?application=${latestApplication.id}` : "/loans/apply"}
            action={latestApplication ? "Track assessment" : "Apply"}
            status="pilot"
          />
          <JourneyCard
            title="Offer & disbursement"
            description={actionableOffer ? `Offer ${actionableOffer.offer_reference}: ${actionableOffer.status}.` : "A formal offer appears here only after an approved assessment."}
            href={actionableOffer ? `/loans/offer?offer=${actionableOffer.id}` : latestApplication ? `/loans/decision?application=${latestApplication.id}` : "/loans/apply"}
            action={actionableOffer ? "Review offer" : "View progress"}
            status="pilot"
          />
        </div>

        {latestApplication ? (
          <section className="panel">
            <p className="eyebrow">Latest application</p>
            <h2>{latestApplication.status}</h2>
            <table className="table">
              <tbody>
                <tr><th>Requested amount</th><td>{formatUgx(Number(latestApplication.amount))}</td></tr>
                <tr><th>Reason</th><td>{latestApplication.reason ?? "Not supplied"}</td></tr>
                <tr><th>Decision</th><td>{latestApplication.credit_decision?.status ?? "Assessment pending"}</td></tr>
              </tbody>
            </table>
          </section>
        ) : (
          <StateNotice state="empty" message="You do not have a credit application in progress." />
        )}

        <section className="panel">
          <h2>Responsible borrowing rule</h2>
          <p className="muted">
            OpFin shows the amount received, interest, fees, total repayment, repayment frequency and offer expiry before acceptance. CPay receives a payout instruction only after explicit acceptance, and the loan is activated only after payment success is confirmed.
          </p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load the Borrow journey.";

    return (
      <Screen title="Borrow" description="Apply, follow assessment, review a formal offer and track disbursement.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
