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
          text: `Offer ${actionableOffer.offer_reference} is ready. Review the amount you receive, all costs, repayment dates and expiry before deciding.`,
          href: `/loans/offer?offer=${actionableOffer.id}`,
          action: "Review offer"
        }
      : latestApplication
        ? {
            eyebrow: "APPLICATION IN PROGRESS",
            title: "Continue from where you stopped",
            text: `Your latest request is ${latestApplication.status}. Assessment, offer and payout remain separate so you can always see what has happened.`,
            href: `/loans/decision?application=${latestApplication.id}`,
            action: "Track request"
          }
        : {
            eyebrow: "BORROW RESPONSIBLY",
            title: "Choose the funding route that fits your need",
            text: "Apply once for configured credit or ask verified investors to fund a reviewed marketplace request. Neither route moves money until the required approvals and confirmations are complete.",
            href: "/loans/apply",
            action: "Check credit options"
          };

    return (
      <Screen title="Borrow" description="One place to access suitable credit, marketplace funding, repayment and hardship support without learning OpFin's internal product structure.">
        <section className="panel compass-next-action">
          <p className="eyebrow">{primary.eyebrow}</p>
          <h2>{primary.title}</h2>
          <p className="muted">{primary.text}</p>
          <Link className="button compass-action" href={primary.href}>{primary.action}</Link>
        </section>

        <section className="panel">
          <h2>Ways to fund your need</h2>
          <div className="grid grid-2">
            <article className="case-card">
              <p className="eyebrow">CREDIT</p>
              <h3>Check my credit options</h3>
              <p className="muted">Tell us the amount and purpose. OpFin routes the request to an active eligible product; you compare the formal offer before accepting.</p>
              <Link className="button" href="/loans/apply">Start one request</Link>
            </article>
            <article className="case-card">
              <p className="eyebrow">OPFIN MARKETPLACE</p>
              <h3>Borrow from investors</h3>
              <p className="muted">Submit a simple funding request. OpFin handles lender-of-record, risk, fees, custody and investor disclosures through independent review.</p>
              <Link className="button secondary" href="/peer-lending/borrow">Request marketplace funding</Link>
            </article>
          </div>
        </section>

        {latestApplication ? (
          <section className="panel">
            <div className="case-card-head">
              <div><p className="eyebrow">LATEST CREDIT REQUEST</p><h2>{latestApplication.status}</h2></div>
              <Link href={`/loans/decision?application=${latestApplication.id}`}>View progress</Link>
            </div>
            <div className="grid grid-3">
              <div><strong>Amount</strong><p>{formatUgx(Number(latestApplication.amount))}</p></div>
              <div><strong>Purpose</strong><p>{latestApplication.reason ?? "Not supplied"}</p></div>
              <div><strong>Assessment</strong><p>{latestApplication.credit_decision?.status ?? "Pending"}</p></div>
            </div>
          </section>
        ) : null}

        <div className="grid grid-2">
          <section className="panel">
            <h2>Already have a loan?</h2>
            <p className="muted">See your schedule, repayment evidence and current balance without starting another request.</p>
            <Link className="button secondary" href="/loans/account">Open loan account</Link>
          </section>
          <section className="panel">
            <h2>Payment difficulty?</h2>
            <p className="muted">Tell OpFin about a financial shock before taking more debt where possible.</p>
            <Link className="button secondary" href="/hardship">Get hardship support</Link>
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Borrow" description="Access suitable funding and manage repayment from one place."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load Borrow."} /></Screen>;
  }
}
