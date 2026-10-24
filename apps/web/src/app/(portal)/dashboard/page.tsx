import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { experienceApi } from "@/lib/api/experience";
import { financialWellbeingApi } from "@/lib/api/financial-wellbeing";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { capabilityLabel } from "@/lib/capabilities";
import { formatUgx } from "@/lib/format";

function money(value: number | null): string {
  return value === null ? "Unavailable" : formatUgx(value);
}

export default async function DashboardPage() {
  const token = await getAccessToken();

  try {
    const [profileResponse, compassResponse, activation] = await Promise.all([
      opfinApi.profile(token),
      financialWellbeingApi.compass(token),
      experienceApi.activation(token)
    ]);
    const user = profileResponse.data.user;
    const compass = compassResponse.data;
    const position = compass.position;
    const [kycResult, consentResult, capabilityResult] = await Promise.allSettled([
      opfinApi.kycStatus(token),
      opfinApi.consents(token),
      opfinApi.capabilities("UG", token)
    ]);

    const kyc = kycResult.status === "fulfilled" ? kycResult.value.data.latest_case : null;
    const consents = consentResult.status === "fulfilled" ? consentResult.value.data.consents : [];
    const activeCreditConsent = consents.find((consent) => consent.purpose === "credit_processing" && consent.status === "granted");
    const capabilities = capabilityResult.status === "fulfilled" ? capabilityResult.value.data.capabilities : {};
    const paymentCapability = capabilities.payments;
    const aspirationResult = await Promise.allSettled([financialWellbeingApi.aspirations(token)]);
    const aspirations = aspirationResult[0].status === "fulfilled" ? aspirationResult[0].value.data.aspirations : [];
    const preferredAspiration = aspirations.find((goal) => goal.status === "active" && goal.is_priority);
    const requiresAttention = ["cashflow_shortfall", "budget_overrun", "loan_due", "debt_due"].includes(compass.next_best_action.code);


    // Put useful financial decisions before optional account completion.
    // Immediate commitments still outrank aspirations; KYC/credit consent
    // remains a distinct gate when a customer selects a regulated service.
    const nextAction = requiresAttention
      ? compass.next_best_action
      : preferredAspiration
        ? {
            title: `Move towards ${preferredAspiration.title}`,
            text: "Your goal is yours to shape. Review it and choose one manageable next step. A plan is not a savings deposit or a product offer.",
            href: "/ahead",
            action: "Review my goal"
          }
        : compass.next_best_action;

    return (
      <Screen
        title={`Welcome, ${user.name}`}
        description="Understand, manage, plan and improve your money. Your Financial Compass separates recorded facts from estimates and keeps each Financial Space private."
      >
        <div className="opfin-today">
          <section className="financial-compass" aria-labelledby="financial-compass-title">
            <div className="case-card-head">
              <div>
                <p className="eyebrow">OpFin Today · Financial Compass</p>
                <h2 className="financial-compass-title" id="financial-compass-title">Your money, clearly in view.</h2>
                <p className="muted">Your recorded position. Estimates and missing information remain clearly labelled.</p>
              </div>
              <Link href="/money">My money plan</Link>
            </div>
            <div className="opfin-today-position" aria-label="Financial position">
              <div>
                <h3>Available money</h3>
                <strong className="stat">{money(position.available_money_minor)}</strong>
                <p className="muted">{position.available_money_confidence ? `Source: ${position.available_money_confidence.replaceAll("_", " ")}` : "No current balance source"}</p>
              </div>
              <div>
                <h3>Coming up · 30 days</h3>
                <strong className="stat">{formatUgx(position.committed_money_minor)}</strong>
                <p className="muted">Confirmed and scheduled commitments</p>
              </div>
              <div>
                <h3>Safe-to-spend estimate</h3>
                <strong className="stat">{money(position.safe_to_spend_minor)}</strong>
                <p className="muted"><Link href="/money">How this is calculated</Link></p>
              </div>
            </div>
          </section>
          <div className="opfin-today-focus">
            <section className="panel opfin-next-step">
              <p className="eyebrow">Your Next Step</p>
              <h2>{nextAction.title}</h2>
              <p className="muted">{nextAction.text}</p>
              <Link className="button compass-action" href={nextAction.href}>{nextAction.action}</Link>
            </section>
            <section className="panel opfin-today-aspiration">
              <p className="eyebrow">OpFin Ahead</p>
              <h2>{preferredAspiration ? preferredAspiration.title : "What matters to you?"}</h2>
              <p className="muted">{preferredAspiration ? "Your chosen priority. Your plans can change whenever life changes." : "Start a personal goal. You do not need a loan, investment or savings product to make a plan."}</p>
              <Link href="/ahead">{preferredAspiration ? "See my goals" : "Choose my goal"} →</Link>
            </section>
          </div>
          <section className="opfin-today-shortcuts" aria-label="Your financial journeys">
            <Link href="/money">My money</Link>
            <Link href="/ahead">My goals</Link>
            <Link href="/save">Save</Link>
            <Link href="/borrow">Borrow</Link>
            <Link href="/grow">Grow</Link>
            <Link href="/more">More</Link>
          </section>
        {!activation.activation_complete ? (
          <section className="panel" style={{ marginBottom: 16 }}>
            <div className="case-card-head">
              <div>
                <h2>Your OpFin setup</h2>
                <p className="muted">Progress is stored with your account and resumes across sessions.</p>
              </div>
              <span className="badge ok">{activation.activation_percent}%</span>
            </div>
            <div className="setup-progress"><span style={{ width: `${activation.activation_percent}%` }} /></div>
          </section>
        ) : null}

        <details className="opfin-today-details">
          <summary>Explore more of your finances and services</summary>
        <div className="grid grid-3 compass-grid">
          <section className="panel">
            <h2>Debt obligations</h2>
            <div className="stat">{formatUgx(position.debt_obligations_minor)}</div>
            <p className="muted">Confirmed outstanding OpFin obligations.</p>
          </section>
          <section className="panel">
            <h2>Savings</h2>
            <div className="stat">{formatUgx(position.current_savings_minor)}</div>
            <p className="muted">Confirmed savings positions only.</p>
          </section>
          <section className="panel">
            <h2>Payments</h2>
            <div className="stat stat-text">{capabilityLabel(paymentCapability)}</div>
            <p className="muted">Payment services depend on actual provider activation and confirmation.</p>
          </section>
        </div>

        <section className="panel">
          <div className="case-card-head">
            <div>
              <h2>Keep essentials running</h2>
              <p className="muted">Electricity, water, internet, rent and other verified essentials can be financed through participating third-party lenders when eligible.</p>
            </div>
            <Link href="/essentials">Open Essentials</Link>
          </div>
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <div className="case-card-head">
              <h2>Money picture</h2>
              <Link href="/money">Open money plan</Link>
            </div>
            <p><strong>This month in:</strong> {formatUgx(compass.cash_flow.income_minor)}</p>
            <p><strong>This month out:</strong> {formatUgx(compass.cash_flow.expense_minor)}</p>
            <p><strong>Net recorded cash flow:</strong> {formatUgx(compass.cash_flow.net_minor)}</p>
            {position.next_income_event ? (
              <p className="muted">Next confirmed or scheduled income: {formatUgx(position.next_income_event.amount_minor)} on {new Date(position.next_income_event.scheduled_for).toLocaleDateString("en-UG")}.</p>
            ) : <p className="muted">No confirmed or scheduled income event is currently recorded.</p>}
          </section>

          <section className="panel">
            <div className="case-card-head">
              <h2>Upcoming</h2>
              <Link href="/calendar">Open calendar</Link>
            </div>
            {compass.calendar.length === 0 ? <p className="muted">No upcoming events in the next 30 days.</p> : (
              <div className="case-list">
                {compass.calendar.slice(0, 4).map((event, index) => (
                  <article className="case-card" key={`${event.id}-${event.scheduled_for}-${index}`}>
                    <div className="case-card-head">
                      <strong>{event.title}</strong>
                      <span className="badge">{event.certainty}</span>
                    </div>
                    <p>{event.direction === "expense" ? "−" : "+"}{formatUgx(event.amount_minor)} · {new Date(event.scheduled_for).toLocaleDateString("en-UG")}</p>
                  </article>
                ))}
              </div>
            )}
          </section>
        </div>

        {compass.goals.length > 0 ? (
          <section className="panel">
            <h2>Goals</h2>
            <div className="grid grid-3">
              {compass.goals.slice(0, 3).map((goal) => (
                <article className="case-card" key={goal.reference}>
                  <strong>{goal.name}</strong>
                  <div className="stat">{formatUgx(goal.balance_minor)}</div>
                  <p className="muted">Target {formatUgx(goal.target_amount_minor)}{goal.target_date ? ` by ${new Date(goal.target_date).toLocaleDateString("en-UG")}` : ""}</p>
                </article>
              ))}
            </div>
          </section>
        ) : null}

        <section className="panel">
          <div className="case-card-head">
            <h2>Trust & account controls</h2>
            <Link href="/more">Open More</Link>
          </div>
          <div className="grid grid-2">
            <div><strong>Identity</strong><p className="muted">{kyc?.status ?? user.nin_status ?? "Not verified"}</p></div>
            <div><strong>Credit data permission</strong><p className="muted">{activeCreditConsent ? "Active" : "Review needed"}</p></div>
          </div>
        </section>
        </details>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load your Financial Compass.";
    return (
      <Screen title="Home" description="Your Financial Compass will appear here when your account data is available.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
