import Link from "next/link";
import { createInclusiveFinanceProgrammeAction } from "@/app/inclusion-actions";
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
  searchParams?: Promise<{
    programme_id?: string;
    status?: string;
    error?: string;
    message?: string;
  }>;
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
        {params?.status === "programme-created" ? (
          <StateNotice state="success" message="Inclusive-finance programme created." />
        ) : null}
        {params?.message ? (
          <StateNotice state={params.error === "validation" ? "validation" : "server"} message={params.message} />
        ) : null}

        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">PROGRAMME CONFIGURATION</p>
              <h2>Create an inclusive-finance programme</h2>
              <p className="muted">
                Configure delivery and eligibility without adding demographic fields to credit scoring.
                Eligibility here governs programme participation only.
              </p>
            </div>
          </div>
          <form action={createInclusiveFinanceProgrammeAction} className="form-grid">
            <div className="field">
              <label htmlFor="code">Programme code</label>
              <input id="code" name="code" placeholder="BIFS-YOUTH-01" required />
            </div>
            <div className="field">
              <label htmlFor="name">Programme name</label>
              <input id="name" name="name" placeholder="Youth financial inclusion pilot" required />
            </div>
            <div className="field">
              <label htmlFor="status">Status</label>
              <select id="status" name="status" defaultValue="draft">
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="starts_at">Start date</label>
              <input id="starts_at" name="starts_at" type="date" />
            </div>
            <div className="field">
              <label htmlFor="ends_at">End date</label>
              <input id="ends_at" name="ends_at" type="date" />
            </div>
            <div className="field">
              <label htmlFor="sponsor_space_id">Sponsor Financial Space ID</label>
              <input id="sponsor_space_id" name="sponsor_space_id" inputMode="numeric" placeholder="Optional" />
            </div>
            <div className="field">
              <label htmlFor="partner_id">Partner ID</label>
              <input id="partner_id" name="partner_id" inputMode="numeric" placeholder="Optional" />
            </div>
            <div className="field">
              <label htmlFor="age_cohort">Age group eligibility</label>
              <select id="age_cohort" name="age_cohort" defaultValue="any">
                <option value="any">Any</option>
                <option value="18_24">18–24</option>
                <option value="25_35">25–35</option>
                <option value="36_44">36–44</option>
                <option value="45_54">45–54</option>
                <option value="55_plus">55+</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="gender">Gender eligibility</label>
              <select id="gender" name="gender" defaultValue="any">
                <option value="any">Any</option>
                <option value="female">Female</option>
                <option value="male">Male</option>
                <option value="another_identity">Another identity</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="disability_status">Disability inclusion eligibility</label>
              <select id="disability_status" name="disability_status" defaultValue="any">
                <option value="any">Any</option>
                <option value="person_with_disability">Person with a disability</option>
                <option value="no_disability_declared">No disability declared</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="refugee_or_displaced_status">Refugee/displacement eligibility</label>
              <select id="refugee_or_displaced_status" name="refugee_or_displaced_status" defaultValue="any">
                <option value="any">Any</option>
                <option value="refugee_or_displaced">Refugee or displaced person</option>
                <option value="not_refugee_or_displaced">Neither</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="rural_urban">Area eligibility</label>
              <select id="rural_urban" name="rural_urban" defaultValue="any">
                <option value="any">Any</option>
                <option value="rural">Rural</option>
                <option value="peri_urban">Peri-urban</option>
                <option value="urban">Urban</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="employment_category">Employment eligibility</label>
              <select id="employment_category" name="employment_category" defaultValue="any">
                <option value="any">Any</option>
                <option value="salaried">Salaried</option>
                <option value="self_employed">Self-employed</option>
                <option value="informal_worker">Informal worker</option>
                <option value="student">Student</option>
                <option value="not_currently_employed">Not currently employed</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="first_time_formal_borrower">First-time formal borrower</label>
              <select id="first_time_formal_borrower" name="first_time_formal_borrower" defaultValue="any">
                <option value="any">Any</option>
                <option value="yes">Required</option>
                <option value="no">Must not be first-time</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="kyc_verified">Verified identity</label>
              <select id="kyc_verified" name="kyc_verified" defaultValue="any">
                <option value="any">Not required by programme</option>
                <option value="required">Required</option>
              </select>
            </div>
            <button className="button" type="submit">Create programme</button>
          </form>
          <p className="muted">
            Voluntary inclusion attributes are used only when a programme explicitly requires them.
            Customers can decline programme measurement; they are then not silently reclassified for credit.
          </p>
        </section>
        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">REPORTING SCOPE</p>
              <h2>{selected ? selected.name : "All inclusive-finance programmes"}</h2>
              <p className="muted">
                Only consented programme-measurement profiles are included. If any group in a dimension is below {impact.privacy.minimum_cohort_size}, that whole dimension is suppressed.
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
            <p className="muted">System-of-record applications within the programme participation window.</p>
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
            <p className="muted">Dimensions with a smaller group are withheld to reduce differencing risk.</p>
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
            <h2>Programme & capability evidence</h2>
            {Object.keys(impact.impact_events).length === 0 &&
            Object.keys(impact.participant_capability_events).length === 0 ? (
              <p className="muted">No programme or participant capability events are recorded in this scope yet.</p>
            ) : (
              <div className="case-list">
                {Object.entries(impact.impact_events).map(([event, count]) => (
                  <div className="case-card" key={`programme:${event}`}>
                    <div className="case-card-head">
                      <div>
                        <strong>{humanise(event)}</strong>
                        <p className="muted">Direct programme event</p>
                      </div>
                      <span className="badge">{count}</span>
                    </div>
                  </div>
                ))}
                {Object.entries(impact.participant_capability_events).map(([event, count]) => (
                  <div className="case-card" key={`capability:${event}`}>
                    <div className="case-card-head">
                      <div>
                        <strong>{humanise(event)}</strong>
                        <p className="muted">Participant capability event after enrolment</p>
                      </div>
                      <span className="badge">{count}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
            <p className="muted">{impact.measurement_notes.capability_events_window}</p>
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
          <p className="muted">{impact.measurement_notes.credit_outcomes_window}</p>
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
