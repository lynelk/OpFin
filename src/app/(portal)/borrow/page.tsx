import Link from "next/link";
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

    const primary = actionableOffer?.status === "offered"
      ? {
          eyebrow: "YOUR OFFER IS READY",
          title: "Review the full cost before you accept",
          text: `Offer ${actionableOffer.offer_reference} is ready. Review amount received, fees, interest, repayment dates and expiry before making a decision.`,
          href: `/loans/offer?offer=${actionableOffer.id}`,
          action: "Review offer"
        }
      : latestApplication
        ? {
            eyebrow: "APPLICATION IN PROGRESS",
            title: "Continue from where your application is now",
            text: `Your latest application is ${latestApplication.status}. OpFin keeps assessment, offer and disbursement as separate states so you always know what has and has not happened.`,
            href: `/loans/decision?application=${latestApplication.id}`,
            action: "Track application"
          }
        : {
            eyebrow: "BORROW RESPONSIBLY",
            title: "See what you may qualify for",
            text: "Start with need, affordability and eligibility. Submitting an application never triggers a payout by itself.",
            href: "/loans/apply",
            action: "Start application"
          };

    return (
      <Screen
        title="Borrow"
        description="One borrowing journey from need and affordability through application, decision, offer, disbursement, repayment and hardship support."
      >
        <section className="panel compass-next-action">
          <p className="eyebrow">{primary.eyebrow}</p>
          <h2>{primary.title}</h2>
          <p className="muted">{primary.text}</p>
          <Link className="button compass-action" href={primary.href}>{primary.action}</Link>
        </section>

        {latestApplication ? (
          <section className="panel">
            <div className="case-card-head">
              <div>
                <p className="eyebrow">Latest application</p>
                <h2>{latestApplication.status}</h2>
              </div>
              <Link href={`/loans/decision?application=${latestApplication.id}`}>View progress</Link>
            </div>
            <table className="table">
              <tbody>
                <tr><th>Requested amount</th><td>{formatUgx(Number(latestApplication.amount))}</td></tr>
                <tr><th>Purpose</th><td>{latestApplication.reason ?? "Not supplied"}</td></tr>
                <tr><th>Assessment</th><td>{latestApplication.credit_decision?.status ?? "Pending"}</td></tr>
              </tbody>
            </table>
          </section>
        ) : (
          <StateNotice state="empty" message="You do not have a credit application in progress." />
        )}

        <div className="grid grid-2">
          <section className="panel">
            <h2>Already have a loan?</h2>
            <p className="muted">Review your schedule, repayment evidence and current loan account without starting a new application.</p>
            <Link className="button secondary" href="/loans/account">Open loan account</Link>
          </section>
          <section className="panel">
            <h2>Payment difficulty?</h2>
            <p className="muted">Tell OpFin about a financial shock before taking more debt where possible. Relief requests remain independently reviewed.</p>
            <Link className="button secondary" href="/hardship">Get hardship support</Link>
          </section>
        </div>

        <section className="panel">
          <h2>Before you accept any credit</h2>
          <p className="muted">
            OpFin shows the amount received, all fees, interest, total repayment, repayment dates, affordability context and offer expiry before acceptance. CPay receives a payout instruction only after explicit acceptance, and a loan is never treated as disbursed merely because an application or offer exists.
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
