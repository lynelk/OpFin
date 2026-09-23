import {
  recordAcquisitionAttributionAction,
  recordCommercialCostAction
} from "@/app/programme-completion-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { inclusiveFinanceApi } from "@/lib/api/inclusive-finance";
import { programmeCompletionApi } from "@/lib/api/programme-completion";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

function percent(value: number | null | undefined): string {
  return value === null || value === undefined ? "Not enough data" : value.toFixed(1) + "%";
}

export default async function CommercialPerformancePage({
  searchParams
}: {
  searchParams?: Promise<{
    programme_id?: string;
    status?: string;
    error?: string;
    message?: string;
  }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();
  const parsedProgramme = params?.programme_id ? Number(params.programme_id) : undefined;
  const programmeId =
    parsedProgramme && Number.isInteger(parsedProgramme) && parsedProgramme > 0
      ? parsedProgramme
      : undefined;

  try {
    const [dashboard, graduation, programmes] = await Promise.all([
      programmeCompletionApi.commercialDashboard(token, programmeId),
      programmeCompletionApi.graduationSummary(token, programmeId),
      inclusiveFinanceApi.programmes(token)
    ]);

    return (
      <Screen
        title="Commercial performance"
        description="Unit economics, credit outcomes and programme-to-commercial graduation using recorded financial truth."
      >
        {params?.status ? (
          <StateNotice state="success" message={params.status.replaceAll("-", " ")} />
        ) : null}
        {params?.message ? (
          <StateNotice
            state={params.error === "validation" ? "validation" : "server"}
            message={params.message}
          />
        ) : null}

        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">REPORTING SCOPE</p>
              <h2>{programmeId ? "Programme cohort" : "Attributed commercial portfolio"}</h2>
              <p className="muted">
                {dashboard.period.from} to {dashboard.period.to}. Missing acquisition or cost records remain missing rather than being estimated.
              </p>
            </div>
          </div>
          <div className="case-list">
            <a className={!programmeId ? "button" : "button secondary"} href="/admin/commercial">All attributed customers</a>
            {programmes.programmes.map((programme) => (
              <a
                className={programme.id === programmeId ? "button" : "button secondary"}
                href={"/admin/commercial?programme_id=" + programme.id}
                key={programme.id}
              >
                {programme.name}
              </a>
            ))}
          </div>
        </section>

        <div className="grid grid-3 compass-grid">
          <section className="panel">
            <p className="muted">Attributed customers</p>
            <div className="stat">{dashboard.acquisition.customers}</div>
            <p className="muted">CAC {dashboard.acquisition.cac_minor == null ? "not available" : formatUgx(dashboard.acquisition.cac_minor)}</p>
          </section>
          <section className="panel">
            <p className="muted">Applications</p>
            <div className="stat">{dashboard.funnel.applications}</div>
            <p className="muted">Approval rate {percent(dashboard.funnel.approval_rate_percent)}</p>
          </section>
          <section className="panel">
            <p className="muted">Disbursed loans</p>
            <div className="stat">{dashboard.funnel.disbursed_loans}</div>
            <p className="muted">{formatUgx(dashboard.funnel.principal_disbursed_minor)} principal</p>
          </section>
          <section className="panel">
            <p className="muted">Repeat borrowers</p>
            <div className="stat">{dashboard.portfolio.repeat_borrowers}</div>
            <p className="muted">Repeat rate {percent(dashboard.portfolio.repeat_rate_percent)}</p>
          </section>
          <section className="panel">
            <p className="muted">NPLs</p>
            <div className="stat">{dashboard.portfolio.npl_count}</div>
            <p className="muted">NPL rate {percent(dashboard.portfolio.npl_rate_percent)}</p>
          </section>
          <section className="panel">
            <p className="muted">Graduated participants</p>
            <div className="stat">{graduation.graduated_participants}</div>
            <p className="muted">Graduation rate {percent(graduation.graduation_rate_percent)}</p>
          </section>
        </div>

        <div className="grid grid-3 compass-grid">
          <section className="panel">
            <p className="muted">OpFin revenue</p>
            <div className="stat stat-text">{formatUgx(dashboard.economics.opfin_revenue_minor)}</div>
          </section>
          <section className="panel">
            <p className="muted">Recorded cost</p>
            <div className="stat stat-text">{formatUgx(dashboard.economics.recorded_cost_minor)}</div>
          </section>
          <section className="panel">
            <p className="muted">Contribution after NPL exposure</p>
            <div className="stat stat-text">{formatUgx(dashboard.economics.contribution_after_npl_exposure_minor)}</div>
            <p className="muted">
              Uses recorded principal-at-NPL exposure, not an invented expected-loss model.
            </p>
          </section>
        </div>

        <div className="grid grid-2 compass-grid">
          <section className="panel">
            <p className="eyebrow">COST TRUTH</p>
            <h2>Record a commercial cost</h2>
            <p className="muted">
              CAC and contribution only improve when real acquisition, KYC, CRB, payment, support, funding or collection costs are recorded.
            </p>
            <form action={recordCommercialCostAction} className="form-grid">
              <div className="field">
                <label htmlFor="cost_type">Cost type</label>
                <select id="cost_type" name="cost_type">
                  {["acquisition","kyc","crb","payment","support","funding","collections","programme_delivery","other"].map((type) => (
                    <option value={type} key={type}>{type.replaceAll("_", " ")}</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="channel">Channel</label>
                <select id="channel" name="channel" defaultValue="">
                  <option value="">Not channel-specific</option>
                  {["organic","employer","programme","referral","agent","whatsapp","ussd","web","app","partner","other"].map((channel) => (
                    <option value={channel} key={channel}>{channel}</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="programme_id_cost">Programme</label>
                <select id="programme_id_cost" name="programme_id" defaultValue={programmeId?.toString() ?? ""}>
                  <option value="">Not programme-specific</option>
                  {programmes.programmes.map((programme) => (
                    <option value={programme.id} key={programme.id}>{programme.name}</option>
                  ))}
                </select>
              </div>
              <div className="field"><label htmlFor="amount_minor">Amount UGX</label><input id="amount_minor" name="amount_minor" type="number" min="0" required /></div>
              <div className="field"><label htmlFor="quantity">Quantity</label><input id="quantity" name="quantity" type="number" min="1" defaultValue="1" /></div>
              <div className="field"><label htmlFor="source_reference">Source reference</label><input id="source_reference" name="source_reference" /></div>
              <div className="field"><label htmlFor="occurred_at">Occurred at</label><input id="occurred_at" name="occurred_at" type="datetime-local" /></div>
              <button className="button" type="submit">Record cost</button>
            </form>
          </section>

          <section className="panel">
            <p className="eyebrow">ACQUISITION ATTRIBUTION</p>
            <h2>Record customer source</h2>
            <p className="muted">
              One canonical acquisition attribution per customer. This is commercial analytics, not programme eligibility.
            </p>
            <form action={recordAcquisitionAttributionAction} className="form-grid">
              <div className="field"><label htmlFor="user_id">Customer user ID</label><input id="user_id" name="user_id" type="number" min="1" required /></div>
              <div className="field">
                <label htmlFor="acquisition_channel">Channel</label>
                <select id="acquisition_channel" name="acquisition_channel">
                  {["organic","employer","programme","referral","agent","whatsapp","ussd","web","app","partner","other"].map((channel) => (
                    <option value={channel} key={channel}>{channel}</option>
                  ))}
                </select>
              </div>
              <div className="field"><label htmlFor="source">Source</label><input id="source" name="source" /></div>
              <div className="field"><label htmlFor="campaign">Campaign</label><input id="campaign" name="campaign" /></div>
              <div className="field">
                <label htmlFor="programme_id_attr">Programme</label>
                <select id="programme_id_attr" name="programme_id" defaultValue={programmeId?.toString() ?? ""}>
                  <option value="">No programme</option>
                  {programmes.programmes.map((programme) => (
                    <option value={programme.id} key={programme.id}>{programme.name}</option>
                  ))}
                </select>
              </div>
              <div className="field"><label htmlFor="acquired_at">Acquired at</label><input id="acquired_at" name="acquired_at" type="datetime-local" /></div>
              <button className="button" type="submit">Save attribution</button>
            </form>
          </section>
        </div>

        <section className="panel">
          <p className="eyebrow">COHORTS</p>
          <h2>Acquisition-month progression</h2>
          {dashboard.cohorts.length === 0 ? (
            <p className="muted">No attributed acquisition cohorts are recorded in this period.</p>
          ) : (
            <div className="case-list">
              {dashboard.cohorts.map((cohort) => (
                <article className="case-card" key={cohort.cohort}>
                  <div className="case-card-head">
                    <div><strong>{cohort.cohort}</strong><p className="muted">{cohort.customers} customers</p></div>
                    <span className="badge">{cohort.repeat_borrowers_to_date} repeat</span>
                  </div>
                  <p className="muted">{cohort.loans_to_date} loans to date</p>
                </article>
              ))}
            </div>
          )}
        </section>

        <section className="panel">
          <p className="eyebrow">COMMERCIAL GRADUATION</p>
          <h2>Programme-supported to independently sustainable finance</h2>
          <p>{graduation.boundary}</p>
          <p className="muted">Criteria: {graduation.criteria.join(", ").replaceAll("_", " ")}</p>
        </section>

        <section className="panel">
          <h2>Measurement notes</h2>
          {Object.entries(dashboard.measurement_notes).map(([key, note]) => (
            <p className="muted" key={key}><strong>{key.replaceAll("_", " ")}:</strong> {note}</p>
          ))}
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen title="Commercial performance" description="Commercial economics and portfolio outcomes.">
        <StateNotice state="server" message={error instanceof Error ? error.message : "Commercial performance could not be loaded."} />
      </Screen>
    );
  }
}
