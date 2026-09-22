import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { inclusiveImpactApi } from "@/lib/api/inclusive-impact";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

function humanise(value: string): string {
  return value.replaceAll("_", " ").replace(/\b\w/g, (character) => character.toUpperCase());
}

export default async function ProgrammeImpactPage({
  searchParams
}: {
  searchParams?: Promise<{ programme_id?: string }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const register = await inclusiveImpactApi.partnerProgrammes(token);
    if (register.programmes.length === 0) {
      return (
        <Screen
          title="Programme impact"
          description="Programme-scoped, privacy-safe delivery and outcome reporting."
        >
          <StateNotice
            state="empty"
            message="No inclusive-finance programme has been assigned to this partner account."
          />
        </Screen>
      );
    }

    const requested = params?.programme_id ? Number(params.programme_id) : undefined;
    const selected =
      register.programmes.find((programme) => programme.id === requested) ??
      register.programmes[0];
    const impact = await inclusiveImpactApi.partnerImpact(selected.id, token);
    const outcomes = impact.outcomes;

    return (
      <Screen
        title="Programme impact"
        description="Delivery, financial-health and livelihood evidence for programmes explicitly assigned to this partner account. Individual customer records are not exposed here."
      >
        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">PROGRAMME SCOPE</p>
              <h2>{selected.name}</h2>
              <p className="muted">
                {selected.code} · {humanise(selected.access_level)} access · aggregate reporting only
              </p>
            </div>
          </div>
          <div className="case-list">
            {register.programmes.map((programme) => (
              <Link
                className={programme.id === selected.id ? "button" : "button secondary"}
                href={`/partner/impact?programme_id=${programme.id}`}
                key={programme.id}
              >
                {programme.name}
              </Link>
            ))}
          </div>
        </section>

        <div className="grid grid-3 compass-grid">
          <section className="panel">
            <p className="muted">People enrolled</p>
            <div className="stat">{impact.delivery.enrolled_people}</div>
            <p className="muted">Historical participants in this programme scope.</p>
          </section>
          <section className="panel">
            <p className="muted">Applications</p>
            <div className="stat">{impact.delivery.applications}</div>
            <p className="muted">Applications inside programme participation windows.</p>
          </section>
          <section className="panel">
            <p className="muted">Average approved amount</p>
            <div className="stat stat-text">
              {formatUgx(impact.delivery.average_approved_amount_minor)}
            </div>
            <p className="muted">Approved decisions only.</p>
          </section>
          <section className="panel">
            <p className="muted">Financial-health coverage</p>
            <div className="stat">
              {outcomes.snapshot_coverage.financial_health_people ?? "Suppressed"}
            </div>
            <p className="muted">Distinct participants with recorded check-ins.</p>
          </section>
          <section className="panel">
            <p className="muted">Livelihood coverage</p>
            <div className="stat">
              {outcomes.snapshot_coverage.livelihood_people ?? "Suppressed"}
            </div>
            <p className="muted">Distinct participants with livelihood observations.</p>
          </section>
          <section className="panel">
            <p className="muted">Empowerment coverage</p>
            <div className="stat">
              {outcomes.snapshot_coverage.empowerment_people ?? "Suppressed"}
            </div>
            <p className="muted">Voluntary programme-measurement observations only.</p>
          </section>
        </div>

        <section className="panel">
          <p className="eyebrow">THEORY OF CHANGE</p>
          <h2>{outcomes.theory_of_change ? "Configured programme pathway" : "No programme pathway configured yet"}</h2>
          {outcomes.theory_of_change ? (
            <>
              {outcomes.theory_of_change.problem_statement ? (
                <p>{outcomes.theory_of_change.problem_statement}</p>
              ) : null}
              <p className="muted">
                Inputs {outcomes.theory_of_change.inputs.length} · Interventions {outcomes.theory_of_change.interventions.length} ·
                Outputs {outcomes.theory_of_change.outputs.length} · Outcomes {outcomes.theory_of_change.outcomes.length} ·
                Impact statements {outcomes.theory_of_change.impact.length}
              </p>
            </>
          ) : (
            <p className="muted">
              Programme configuration remains with OpFin platform operations. Partner access does not create or modify credit policy.
            </p>
          )}
        </section>

        <section className="panel">
          <p className="eyebrow">OUTCOME EVIDENCE</p>
          <h2>Configured indicators</h2>
          <p className="muted">{outcomes.causality_notice}</p>
          {outcomes.indicator_summaries.length === 0 ? (
            <StateNotice state="empty" message="No impact indicators are assigned to this programme yet." />
          ) : (
            <div className="case-list">
              {outcomes.indicator_summaries.map((summary) => (
                <article className="case-card" key={summary.indicator.id}>
                  <div className="case-card-head">
                    <div>
                      <strong>{summary.indicator.name}</strong>
                      <p className="muted">
                        {summary.indicator.code} · {humanise(summary.indicator.outcome_domain)}
                      </p>
                    </div>
                    <span className="badge">{summary.observation_count} observations</span>
                  </div>
                  {summary.suppressed ? (
                    <p className="muted">
                      Participant values are suppressed because the measured cohort is below the privacy threshold of {outcomes.privacy.minimum_cohort_size}.
                    </p>
                  ) : (
                    <p className="muted">
                      Participants {summary.participant_count ?? 0}
                      {summary.average_numeric !== undefined ? ` · Average ${summary.average_numeric}` : ""}
                      {summary.latest_observed_at ? ` · Latest ${summary.latest_observed_at}` : ""}
                    </p>
                  )}
                </article>
              ))}
            </div>
          )}
        </section>

        <section className="panel">
          <p className="eyebrow">ACCESS BOUNDARY</p>
          <h2>Privacy-safe by design</h2>
          <p className="muted">
            This portal is programme-scoped and aggregate-only. It does not expose individual customer records,
            alter underwriting, or make causal claims that the underlying evaluation design cannot support.
          </p>
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen
        title="Programme impact"
        description="Programme-scoped, privacy-safe delivery and outcome reporting."
      >
        <StateNotice
          state="server"
          message={error instanceof Error ? error.message : "Programme impact could not be loaded."}
        />
      </Screen>
    );
  }
}
