import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { inclusiveFinanceApi } from "@/lib/api/inclusive-finance";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

function humanise(value: string): string {
  return value.replaceAll("_", " ").replace(/\b\w/g, (character) => character.toUpperCase());
}

export default async function InclusionPage({
  searchParams
}: {
  searchParams?: Promise<{ programme_id?: string }>;
}) {
  const params = await searchParams;
  const parsedProgramme = params?.programme_id ? Number(params.programme_id) : undefined;
  const programmeId = parsedProgramme && Number.isInteger(parsedProgramme) && parsedProgramme > 0
    ? parsedProgramme
    : undefined;
  const token = await getAccessToken();

  try {
    const [register, impact] = await Promise.all([
      inclusiveFinanceApi.programmes(token),
      inclusiveFinanceApi.impact(programmeId, token)
    ]);

    const selected = programmeId
      ? register.programmes.find((programme) => programme.id === programmeId)
      : undefined;
    const approved = impact.decisions.approved ?? 0;
    const declined = impact.decisions.declined ?? 0;
    const referred = impact.decisions.referred ?? 0;

    return (
      <Screen
        title="Inclusion & programmes"
        description="Programme delivery, financial-capability evidence and privacy-safe inclusion outcomes. Voluntary programme demographics remain outside credit-risk decisioning."
      >
        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">REPORTING SCOPE</p>
              <h2>{selected ? selected.name : "All inclusive-finance programmes"}</h2>
              <p className="muted">
                Cohorts smaller than {impact.privacy.minimum_cohort_size} people are suppressed and only consented programme-measurement profiles are included.
              </p>
            </div>
            {selected ? <Link className="button secondary" href="/admin/inclusion">All programmes</Link> : null}
          </div>
        </section>

        <div className="grid grid-3 compass-grid">
          <section className="panel">
            <p className="muted">People enrolled</p>
            <div className="stat">{impact.enrolled_people}</div>
            <p className="muted">Distinct enrolled customers in the reporting scope.</p>
          </section>
          <section className="panel">
            <p className="muted">Applications</p>
            <div className="stat">{impact.applications}</div>
            <p className="muted">System-of-record loan applications from enrolled customers.</p>
          </section>
          <section className="panel">
            <p className="muted">Approved decisions</p>
            <div className="stat">{approved}</div>
            <p className="muted">Declined {declined} · referred {referred}</p>
          </section>
          <section className="panel">
            <p className="muted">Average approved amount</p>
            <div className="stat stat-text">{formatUgx(impact.average_approved_amount_minor)}</div>
            <p className="muted">Approved decisions only.</p>
          </section>
          <section className="panel">
            <p className="muted">NPLs tracked</p>
            <div className="stat">{impact.npl_count}</div>
            <p className="muted">Loans with a recorded non-performing date in scope.</p>
          </section>
          <section className="panel">
            <p className="muted">Privacy threshold</p>
            <div className="stat">{impact.privacy.minimum_cohort_size}</div>
            <p className="muted">Smaller demographic cohorts are not returned.</p>
          </section>
        </div>

        <section className="panel compass-grid">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">PROGRAMME REGISTER</p>
              <h2>Configured interventions</h2>
              <p className="muted">Programme targeting and reporting do not become credit-risk variables.</p>
            </div>
          </div>
          {register.programmes.length === 0 ? (
            <StateNotice state="empty" message="No inclusive-finance programmes have been configured." />
          ) : (
            <div className="case-list">
              {register.programmes.map((programme) => (
                <article className="case-card" key={programme.id}>
                  <div className="case-card-head">
                    <div>
                      <span className={`badge ${programme.status === "active" ? "ok" : "warn"}`}>{programme.status}</span>
                      <h3>{programme.name}</h3>
                      <p className="muted">{programme.code}</p>
                    </div>
                    <Link className="button secondary" href={`/admin/inclusion?programme_id=${programme.id}`}>View outcomes</Link>
                  </div>
                  <p>
                    <strong>{programme.enrolled_people ?? 0}</strong> enrolled
                    {programme.partner_id ? ` · Partner #${programme.partner_id}` : ""}
                    {programme.sponsor_space_id ? ` · Sponsor Space #${programme.sponsor_space_id}` : ""}
                  </p>
                  <p className="muted">
                    {programme.starts_at ? `Starts ${new Date(programme.starts_at).toLocaleDateString("en-UG")}` : "No fixed start date"}
                    {" · "}
                    {programme.ends_at ? `Ends ${new Date(programme.ends_at).toLocaleDateString("en-UG")}` : "No fixed end date"}
                  </p>
                </article>
              ))}
            </div>
          )}
        </section>

        <div className="grid grid-2 compass-grid">
          <section className="panel">
            <h2>Capability & programme events</h2>
            {Object.keys(impact.impact_events).length === 0 ? (
              <p className="muted">No programme outcome events are recorded in this scope yet.</p>
            ) : (
              <div className="case-list">
                {Object.entries(impact.impact_events).map(([event, count]) => (
                  <div className="case-card" key={event}>
                    <div className="case-card-head">
                      <strong>{humanise(event)}</strong>
                      <span className="badge">{count}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>

          <section className="panel">
            <h2>Consented inclusion cohorts</h2>
            {Object.keys(impact.cohorts).length === 0 ? (
              <p className="muted">No cohort meets the minimum reporting threshold in this scope.</p>
            ) : (
              <div className="case-list">
                {Object.entries(impact.cohorts).flatMap(([dimension, groups]) =>
                  Object.entries(groups).map(([label, count]) => (
                    <div className="case-card" key={`${dimension}:${label}`}>
                      <div className="case-card-head">
                        <div>
                          <strong>{humanise(dimension)}</strong>
                          <p className="muted">{humanise(label)}</p>
                        </div>
                        <span className="badge">{count}</span>
                      </div>
                    </div>
                  ))
                )}
              </div>
            )}
          </section>
        </div>

        <section className="panel">
          <h2>Decision boundary</h2>
          <p>
            Gender, age cohort, disability status, refugee/displaced-person status, rural/urban classification,
            employment category and self-declared first-time-borrower status are programme-measurement fields.
            They are not inputs to OpFin credit decisioning.
          </p>
          <p className="muted">
            Alternative provider signals require verifiable provenance and active credit-processing consent before
            they may be marked eligible for a governed scoring policy. Verification alone never changes a score,
            limit, price or approval.
          </p>
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen title="Inclusion & programmes" description="Programme delivery and inclusion outcomes.">
        <StateNotice
          state="server"
          message={error instanceof Error ? error.message : "Unable to load inclusive-finance reporting."}
        />
      </Screen>
    );
  }
}
