import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { createFinancialCalendarEventAction } from "@/app/financial-wellbeing-actions";
import { financialWellbeingApi } from "@/lib/api/financial-wellbeing";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function FinancialCalendarPage({
  searchParams
}: Readonly<{
  searchParams?: Promise<{ status?: string; error?: string; message?: string }>;
}>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};

  try {
    const [calendarResponse, categoriesResponse] = await Promise.all([
      financialWellbeingApi.calendar(token),
      financialWellbeingApi.categories(token)
    ]);
    const calendar = calendarResponse.data;
    const categories = categoriesResponse.data.categories;
    const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);

    return (
      <Screen
        title="Financial Calendar"
        description="Plan expected income and obligations without confusing estimates with money that has actually arrived."
      >
        {params.status ? <StateNotice state="success" message="Your financial calendar was updated." /> : null}
        {params.error ? (
          <StateNotice
            state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"}
            message={params.message ?? "Unable to update the financial calendar."}
          />
        ) : null}

        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">Forecast window</p>
              <h2>{calendar.from} to {calendar.to}</h2>
            </div>
            <Link className="button secondary" href="/money">Open money plan</Link>
          </div>
          <div className="grid grid-2">
            {Object.entries(calendar.certainty_legend).map(([certainty, explanation]) => (
              <div className="case-card" key={certainty}>
                <strong>{certainty.charAt(0).toUpperCase() + certainty.slice(1)}</strong>
                <p className="muted">{explanation}</p>
              </div>
            ))}
          </div>
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Upcoming events</h2>
            {calendar.events.length === 0 ? <p className="muted">Nothing is currently scheduled in this forecast window.</p> : (
              <div className="case-list">
                {calendar.events.map((event, index) => (
                  <article className="case-card" key={`${event.id}-${event.scheduled_for}-${index}`}>
                    <div className="case-card-head">
                      <strong>{event.title}</strong>
                      <span className="badge">{event.certainty}</span>
                    </div>
                    <div className="stat">{event.direction === "expense" ? "−" : "+"}{formatUgx(event.amount_minor)}</div>
                    <p>{new Date(event.scheduled_for).toLocaleDateString("en-UG", { dateStyle: "medium" })}</p>
                    <p className="muted">
                      {event.event_type.replaceAll("_", " ")}
                      {event.category ? ` · ${event.category}` : ""}
                      {event.recurrence ? ` · repeats ${event.recurrence}` : ""}
                      {event.derived ? " · from OpFin records" : ""}
                    </p>
                  </article>
                ))}
              </div>
            )}
          </section>

          <section className="panel">
            <h2>Add an expected event</h2>
            <p className="muted">Choose Scheduled for a real planned payment or income event. Use Estimated when the amount or timing is still uncertain.</p>
            <form action={createFinancialCalendarEventAction} className="form-grid">
              <div className="field">
                <label htmlFor="title">Event</label>
                <input id="title" name="title" required maxLength={160} placeholder="e.g. Salary, rent or school fees" />
              </div>
              <div className="grid grid-2">
                <div className="field">
                  <label htmlFor="event_type">Type</label>
                  <select id="event_type" name="event_type" defaultValue="bill">
                    <option value="income">Income</option>
                    <option value="bill">Bill</option>
                    <option value="loan">Loan</option>
                    <option value="savings">Savings</option>
                    <option value="insurance">Insurance</option>
                    <option value="investment">Investment</option>
                    <option value="subscription">Subscription</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="direction">Cash direction</label>
                  <select id="direction" name="direction" defaultValue="expense">
                    <option value="expense">Money out</option>
                    <option value="income">Money in</option>
                  </select>
                </div>
              </div>
              <div className="grid grid-2">
                <div className="field">
                  <label htmlFor="amount_minor">Amount (UGX)</label>
                  <input id="amount_minor" name="amount_minor" type="number" min="0" step="1" required />
                </div>
                <div className="field">
                  <label htmlFor="scheduled_for">Date</label>
                  <input id="scheduled_for" name="scheduled_for" type="date" defaultValue={tomorrow} required />
                </div>
              </div>
              <div className="grid grid-2">
                <div className="field">
                  <label htmlFor="certainty">Certainty</label>
                  <select id="certainty" name="certainty" defaultValue="scheduled">
                    <option value="scheduled">Scheduled</option>
                    <option value="estimated">Estimated</option>
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="recurrence">Repeat</label>
                  <select id="recurrence" name="recurrence" defaultValue="">
                    <option value="">One time</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                  </select>
                </div>
              </div>
              <div className="field">
                <label htmlFor="category">Category</label>
                <select id="category" name="category" defaultValue="">
                  <option value="">No category</option>
                  {categories.map((category) => <option value={category} key={category}>{category}</option>)}
                </select>
              </div>
              <div className="field">
                <label htmlFor="notes">Notes</label>
                <textarea id="notes" name="notes" rows={3} maxLength={1000} placeholder="Optional context" />
              </div>
              <button className="button" type="submit">Add event</button>
            </form>
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load the financial calendar.";
    return (
      <Screen title="Financial Calendar" description="Your upcoming financial events will appear here when the service is available.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
