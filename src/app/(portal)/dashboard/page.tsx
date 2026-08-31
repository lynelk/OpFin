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
  return value === null ? "Add balance" : formatUgx(value);
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

    const nextAction = !activation.activation_complete
      ? {
          title: "Finish your OpFin setup",
          text: `${activation.essential_complete} of ${activation.essential_total} essential setup steps are complete. Finish the essentials once, then OpFin will ask for additional information only when a journey needs it.`,
          href: "/setup",
          action: "Continue setup"
        }
      : !kyc || kyc.status !== "verified"
        ? { title: "Complete verification", text: "Verify your identity so OpFin can safely unlock regulated financial products.", href: "/kyc", action: "Continue verification" }
        : !activeCreditConsent
          ? { title: "Review your permissions", text: "Grant only the permissions needed before OpFin uses your information for credit assessment.", href: "/consent", action: "Review permissions" }
          : compass.next_best_action;

    return (
      <Screen
        title={`Welcome, ${user.name}`}
        description="Your Financial Compass shows recorded money, commitments, goals and the clearest next step without presenting estimates as confirmed cash."
      >
        <section className="panel compass-next-action">
          <p className="eyebrow">Recommended next step</p>
          <h2>{nextAction.title}</h2>
          <p className="muted">{nextAction.text}</p>
          <Link className="button compass-action" href={nextAction.href}>{nextAction.action}</Link>
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

        <div className="grid grid-3 compass-grid">
          <section className="panel">
            <h2>Available money</h2>
            <div className="stat">{money(position.available_money_minor)}</div>
            <p className="muted">{position.available_money_confidence ? `Source confidence: ${position.available_money_confidence.replaceAll("_", " ")}.` : "No current balance source is recorded."}</p>
          </section>
          <section className="panel">
            <h2>Committed money</h2>
            <div className="stat">{formatUgx(position.committed_money_minor)}</div>
            <p className="muted">Confirmed and scheduled obligations in the next 30 days.</p>
          </section>
          <section className="panel">
            <h2>Safe to spend</h2>
            <div className="stat">{money(position.safe_to_spend_minor)}</div>
            <p className="muted">{position.safe_to_spend_explanation}</p>
          </section>
        </div>

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
            <p className="muted">Money movement is executed through CPay when enabled.</p>
          </section>
        </div>

        <section className="panel">
          <div className="case-card-head">
            <div>
              <h2>What would you like to do?</h2>
              <p className="muted">Four clear financial journeys. Setup, security, automation and support stay in More so Home remains focused.</p>
            </div>
          </div>
          <div className="quick-actions" aria-label="Primary financial journeys">
            <Link className="button" href="/borrow">Borrow</Link>
            <Link className="button secondary" href="/save">Save</Link>
            <Link className="button secondary" href="/grow">Grow</Link>
            <Link className="button secondary" href="/insurance">Protect</Link>
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
