import Link from "next/link";
import {
  assignImpactIndicatorAction,
  createImpactIndicatorAction,
  grantProgrammePartnerAccessAction,
  updateProgrammeTheoryAction
} from "@/app/inclusion-impact-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { inclusiveFinanceApi } from "@/lib/api/inclusive-finance";
import { inclusiveImpactApi } from "@/lib/api/inclusive-impact";
import { getAccessToken } from "@/lib/auth/session";

function humanise(value: string): string {
  return value.replaceAll("_", " ").replace(/\b\w/g, (character) => character.toUpperCase());
}

function lines(values: unknown[] | undefined): string {
  return (values ?? []).map((value) => String(value)).join("\n");
}

export default async function InclusionFrameworkPage({
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

  try {
    const register = await inclusiveFinanceApi.programmes(token);
    const parsed = params?.programme_id ? Number(params.programme_id) : undefined;
    const selected =
      register.programmes.find((programme) => programme.id === parsed) ??
      register.programmes[0];

    if (!selected) {
      return (
        <Screen
          title="Impact framework"
          description="Theory of change, outcome indicators and privacy-safe programme measurement."
        >
          <StateNotice
            state="empty"
            message="Create an inclusive-finance programme before configuring its impact framework."
          />
          <Link className="button" href="/admin/inclusion">
            Back to programmes
          </Link>
        </Screen>
      );
    }

    const [indicatorRegister, framework, outcomes] = await Promise.all([
      inclusiveImpactApi.indicators(token),
      inclusiveImpactApi.programmeFramework(selected.id, token),
      inclusiveImpactApi.programmeOutcomes(selected.id, token)
    ]);
    const theory = framework.theory_of_change;

    return (
      <Screen
        title="Impact framework"
        description="Configure evidence and programme outcomes without changing OpFin underwriting."
      >
        {params?.status === "indicator-created" ? (
          <StateNotice state="success" message="Impact indicator added to the registry." />
        ) : null}
        {params?.status === "theory-updated" ? (
          <StateNotice state="success" message="Programme theory of change updated." />
        ) : null}
        {params?.status === "indicator-assigned" ? (
          <StateNotice state="success" message="Indicator assigned to the programme." />
        ) : null}
        {params?.status === "partner-access-granted" ? (
          <StateNotice state="success" message="Programme partner access granted." />
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
              <p className="eyebrow">PROGRAMME</p>
              <h2>{selected.name}</h2>
              <p className="muted">{selected.code} · {selected.status}</p>
            </div>
            <Link className="button secondary" href="/admin/inclusion">
              Programme register
            </Link>
          </div>
          <div className="case-list">
            {register.programmes.map((programme) => (
              <Link
                className={programme.id === selected.id ? "button" : "button secondary"}
                href={`/admin/inclusion/framework?programme_id=${programme.id}`}
                key={programme.id}
              >
                {programme.name}
              </Link>
            ))}
          </div>
        </section>

        <section className="panel">
          <p className="eyebrow">THEORY OF CHANGE</p>
          <h2>Programme pathway</h2>
          <p className="muted">
            One item per line. This is a programme measurement framework, not a credit-policy editor.
          </p>
          <form action={updateProgrammeTheoryAction} className="form-grid">
            <input type="hidden" name="programme_id" value={selected.id} />
            <div className="field">
              <label htmlFor="problem_statement">Problem statement</label>
              <textarea
                id="problem_statement"
                name="problem_statement"
                defaultValue={theory?.problem_statement ?? ""}
                rows={4}
              />
            </div>
            <div className="field">
              <label htmlFor="inputs">Inputs</label>
              <textarea id="inputs" name="inputs" defaultValue={lines(theory?.inputs)} rows={5} />
            </div>
            <div className="field">
              <label htmlFor="interventions">Interventions</label>
              <textarea
                id="interventions"
                name="interventions"
                defaultValue={lines(theory?.interventions)}
                rows={5}
              />
            </div>
            <div className="field">
              <label htmlFor="outputs">Outputs</label>
              <textarea id="outputs" name="outputs" defaultValue={lines(theory?.outputs)} rows={5} />
            </div>
            <div className="field">
              <label htmlFor="outcomes">Outcomes</label>
              <textarea id="outcomes" name="outcomes" defaultValue={lines(theory?.outcomes)} rows={5} />
            </div>
            <div className="field">
              <label htmlFor="impact">Longer-term impact</label>
              <textarea id="impact" name="impact" defaultValue={lines(theory?.impact)} rows={5} />
            </div>
            <div className="field">
              <label htmlFor="assumptions">Assumptions</label>
              <textarea
                id="assumptions"
                name="assumptions"
                defaultValue={lines(theory?.assumptions)}
                rows={4}
              />
            </div>
            <div className="field">
              <label htmlFor="risks">Risks</label>
              <textarea id="risks" name="risks" defaultValue={lines(theory?.risks)} rows={4} />
            </div>
            <div className="field">
              <label htmlFor="evidence_sources">Evidence sources</label>
              <textarea
                id="evidence_sources"
                name="evidence_sources"
                defaultValue={lines(theory?.evidence_sources)}
                rows={4}
              />
            </div>
            <div className="field">
              <label htmlFor="version">Version</label>
              <input id="version" name="version" defaultValue={theory?.version ?? "1.0"} />
            </div>
            <div className="field">
              <label htmlFor="theory_status">Framework status</label>
              <select id="theory_status" name="theory_status" defaultValue={theory?.status ?? "draft"}>
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="retired">Retired</option>
              </select>
            </div>
            <button className="button" type="submit">
              Save theory of change
            </button>
          </form>
        </section>

        <div className="grid grid-2 compass-grid">
          <section className="panel">
            <p className="eyebrow">INDICATOR REGISTRY</p>
            <h2>Create an indicator</h2>
            <form action={createImpactIndicatorAction} className="form-grid">
              <input type="hidden" name="programme_id" value={selected.id} />
              <div className="field">
                <label htmlFor="code">Indicator code</label>
                <input id="code" name="code" placeholder="OF-FH-001" required />
              </div>
              <div className="field">
                <label htmlFor="name">Indicator name</label>
                <input id="name" name="name" required />
              </div>
              <div className="field">
                <label htmlFor="description">Description</label>
                <textarea id="description" name="description" rows={3} />
              </div>
              <div className="field">
                <label htmlFor="outcome_domain">Outcome domain</label>
                <select id="outcome_domain" name="outcome_domain" required>
                  {indicatorRegister.outcome_domains.map((domain) => (
                    <option value={domain} key={domain}>{humanise(domain)}</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="value_type">Value type</label>
                <select id="value_type" name="value_type" required>
                  {indicatorRegister.value_types.map((valueType) => (
                    <option value={valueType} key={valueType}>{humanise(valueType)}</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="unit">Unit</label>
                <input id="unit" name="unit" placeholder="UGX, people, %, days..." />
              </div>
              <div className="field">
                <label htmlFor="frequency">Reporting frequency</label>
                <input id="frequency" name="frequency" placeholder="baseline / 90-day / annual" />
              </div>
              <div className="field">
                <label htmlFor="privacy_classification">Privacy classification</label>
                <select id="privacy_classification" name="privacy_classification" defaultValue="programme_measurement">
                  <option value="programme_measurement">Programme measurement</option>
                  <option value="aggregate_only">Aggregate only</option>
                  <option value="operational">Operational</option>
                </select>
              </div>
              <label>
                <input type="checkbox" name="baseline_required" /> Baseline required
              </label>
              <label>
                <input type="checkbox" name="verification_required" /> Verification required
              </label>
              <input type="hidden" name="framework" value="opfin_impact" />
              <input type="hidden" name="framework_version" value="1.0" />
              <button className="button" type="submit">Add indicator</button>
            </form>
          </section>

          <section className="panel">
            <p className="eyebrow">PROGRAMME INDICATORS</p>
            <h2>Assign from the registry</h2>
            {indicatorRegister.indicators.length === 0 ? (
              <StateNotice state="empty" message="Create an indicator first." />
            ) : (
              <form action={assignImpactIndicatorAction} className="form-grid">
                <input type="hidden" name="programme_id" value={selected.id} />
                <div className="field">
                  <label htmlFor="indicator_definition_id">Indicator</label>
                  <select id="indicator_definition_id" name="indicator_definition_id" required>
                    {indicatorRegister.indicators.map((indicator) => (
                      <option value={indicator.id} key={indicator.id}>
                        {indicator.code} · {indicator.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="target_numeric">Numeric target</label>
                  <input id="target_numeric" name="target_numeric" inputMode="decimal" />
                </div>
                <div className="field">
                  <label htmlFor="target_text">Text target</label>
                  <input id="target_text" name="target_text" />
                </div>
                <div className="field">
                  <label htmlFor="reporting_frequency">Programme reporting frequency</label>
                  <input id="reporting_frequency" name="reporting_frequency" />
                </div>
                <label>
                  <input type="checkbox" name="baseline_required" /> Baseline required for this programme
                </label>
                <button className="button" type="submit">Assign indicator</button>
              </form>
            )}
          </section>
        </div>

        <section className="panel">
          <p className="eyebrow">MEASUREMENT STATUS</p>
          <h2>Assigned indicators</h2>
          {outcomes.indicator_summaries.length === 0 ? (
            <StateNotice state="empty" message="No indicators are assigned to this programme yet." />
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
                  <p className="muted">
                    {summary.suppressed
                      ? `Participant values suppressed below cohort size ${outcomes.privacy.minimum_cohort_size}.`
                      : `Participants ${summary.participant_count}`}
                  </p>
                </article>
              ))}
            </div>
          )}
          <p className="muted">{outcomes.causality_notice}</p>
        </section>

        <section className="panel">
          <p className="eyebrow">PARTNER ACCESS</p>
          <h2>Grant programme-scoped reporting access</h2>
          <p className="muted">
            The partner must already be configured on this programme, and the target user must be a dedicated
            programme-partner account. The grant is aggregate-only and never exposes individual customer records.
          </p>
          <form action={grantProgrammePartnerAccessAction} className="form-grid">
            <input type="hidden" name="programme_id" value={selected.id} />
            <div className="field">
              <label htmlFor="partner_id">Partner ID</label>
              <input
                id="partner_id"
                name="partner_id"
                inputMode="numeric"
                defaultValue={selected.partner_id ?? ""}
                required
              />
            </div>
            <div className="field">
              <label htmlFor="user_id">Programme-partner user ID</label>
              <input id="user_id" name="user_id" inputMode="numeric" required />
            </div>
            <div className="field">
              <label htmlFor="access_level">Access level</label>
              <select id="access_level" name="access_level" defaultValue="read_only">
                <option value="read_only">Read only</option>
                <option value="auditor">Auditor</option>
                <option value="mel_officer">MEL officer</option>
                <option value="programme_admin">Programme administrator</option>
              </select>
            </div>
            <button className="button" type="submit">Grant programme access</button>
          </form>
        </section>

        <section className="panel">
          <h2>Non-negotiable boundary</h2>
          <p>{indicatorRegister.governance.principle}</p>
          <p className="muted">
            Partner-specific frameworks, donor terminology and targets are configuration. They do not redefine
            OpFin's core product or turn programme demographics into risk signals.
          </p>
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen
        title="Impact framework"
        description="Theory of change, outcome indicators and privacy-safe programme measurement."
      >
        <StateNotice
          state="server"
          message={error instanceof Error ? error.message : "Impact framework could not be loaded."}
        />
      </Screen>
    );
  }
}
