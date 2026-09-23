import Link from "next/link";
import {
  addProgrammeQuestionAction,
  addQuestionTranslationAction,
  applyProgrammeTemplateAction,
  configureProviderAdapterAction,
  createProgrammeInstrumentAction,
  generateProgrammeFollowUpsAction,
  ingestProviderEvidenceAction,
  inviteProgrammePartnerAction,
  revokeProgrammePartnerAccessAction
} from "@/app/programme-completion-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { inclusiveFinanceApi } from "@/lib/api/inclusive-finance";
import { programmeCompletionApi } from "@/lib/api/programme-completion";
import { getAccessToken } from "@/lib/auth/session";

function humanise(value: string): string {
  return value.replaceAll("_", " ").replace(/\b\w/g, (character) => character.toUpperCase());
}

export default async function ProgrammeDeliveryPage({
  searchParams
}: {
  searchParams?: Promise<{
    programme_id?: string;
    status?: string;
    error?: string;
    message?: string;
    activation_token?: string;
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

    const [operations, instruments, templates, adapters, partnerUsers] = await Promise.all([
      programmeCompletionApi.operations(selected?.id, token),
      programmeCompletionApi.instruments(selected?.id, token),
      programmeCompletionApi.templates(token),
      programmeCompletionApi.adapters(token, selected?.id),
      selected
        ? programmeCompletionApi.partnerUsers(selected.id, token)
        : Promise.resolve({ programme_id: 0, users: [], invitations: [], access_boundary: "" })
    ]);

    return (
      <Screen
        title="Programme delivery"
        description="Configure instruments, follow-ups, channels, localisation and partner access without changing credit policy."
      >
        {params?.status ? (
          <StateNotice state="success" message={humanise(params.status)} />
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
              <p className="eyebrow">PROGRAMME SCOPE</p>
              <h2>{selected?.name ?? "No programme selected"}</h2>
              <p className="muted">
                Programme delivery uses the same consent, enrolment and privacy boundaries as the Impact framework.
              </p>
            </div>
            <Link className="button secondary" href="/admin/inclusion">Programme register</Link>
          </div>
          <div className="case-list">
            {register.programmes.map((programme) => (
              <Link
                className={programme.id === selected?.id ? "button" : "button secondary"}
                href={"/admin/inclusion/delivery?programme_id=" + programme.id}
                key={programme.id}
              >
                {programme.name}
              </Link>
            ))}
          </div>
        </section>

        <div className="grid grid-3 compass-grid">
          <section className="panel"><p className="muted">Active enrolments</p><div className="stat">{operations.active_enrolments}</div></section>
          <section className="panel"><p className="muted">Due next 7 days</p><div className="stat">{operations.due_next_7_days}</div></section>
          <section className="panel"><p className="muted">Overdue</p><div className="stat">{operations.overdue}</div></section>
          <section className="panel"><p className="muted">Completed follow-ups</p><div className="stat">{operations.completed}</div></section>
          <section className="panel"><p className="muted">Consent exceptions</p><div className="stat">{operations.consent_exceptions}</div></section>
          <section className="panel"><p className="muted">Missing baseline</p><div className="stat">{operations.baseline_missing}</div></section>
        </div>

        {selected ? (
          <div className="grid grid-2 compass-grid">
            <section className="panel">
              <p className="eyebrow">PROGRAMME TEMPLATE</p>
              <h2>Start from a governed template</h2>
              <p className="muted">
                Templates create editable draft instruments and a draft theory of change. They do not activate a
                programme or assert a partnership.
              </p>
              <form action={applyProgrammeTemplateAction} className="form-grid">
                <input type="hidden" name="programme_id" value={selected.id} />
                <div className="field">
                  <label htmlFor="template_code">Template</label>
                  <select id="template_code" name="template_code" required>
                    {templates.templates.map((template) => (
                      <option value={template.code} key={template.code}>{template.name}</option>
                    ))}
                  </select>
                </div>
                <button className="button" type="submit">Apply template</button>
              </form>
            </section>

            <section className="panel">
              <p className="eyebrow">FOLLOW-UP SCHEDULE</p>
              <h2>Generate due measurements</h2>
              <p className="muted">
                The hourly scheduler maintains follow-ups automatically. This action safely reconciles them now.
              </p>
              <form action={generateProgrammeFollowUpsAction}>
                <input type="hidden" name="programme_id" value={selected.id} />
                <button className="button" type="submit">Generate follow-ups</button>
              </form>
            </section>
          </div>
        ) : null}

        {selected ? (
          <section className="panel">
            <p className="eyebrow">INSTRUMENT DESIGN</p>
            <h2>Create a programme check-in</h2>
            <form action={createProgrammeInstrumentAction} className="form-grid">
              <input type="hidden" name="programme_id" value={selected.id} />
              <div className="field"><label htmlFor="code">Code</label><input id="code" name="code" required /></div>
              <div className="field"><label htmlFor="name">Name</label><input id="name" name="name" required /></div>
              <div className="field"><label htmlFor="description">Description</label><textarea id="description" name="description" rows={3} /></div>
              <div className="field">
                <label htmlFor="outcome_domain">Outcome domain</label>
                <select id="outcome_domain" name="outcome_domain" defaultValue="financial_health_resilience">
                  <option value="access_inclusion">Access & inclusion</option>
                  <option value="financial_health_resilience">Financial health & resilience</option>
                  <option value="livelihood_enterprise">Livelihood & enterprise</option>
                  <option value="dignified_work">Dignified work</option>
                  <option value="agency_empowerment">Agency & empowerment</option>
                  <option value="market_systems">Market systems</option>
                  <option value="climate_resilience">Climate/resilience</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="default_measurement_stage">Default stage</label>
                <select id="default_measurement_stage" name="default_measurement_stage" defaultValue="check_in">
                  <option value="baseline">Baseline</option>
                  <option value="30_day">30 day</option>
                  <option value="90_day">90 day</option>
                  <option value="6_month">6 month</option>
                  <option value="12_month">12 month</option>
                  <option value="24_month">24 month</option>
                  <option value="exit">Exit</option>
                  <option value="post_programme">Post-programme</option>
                  <option value="check_in">Check-in</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="consent_classification">Consent classification</label>
                <select id="consent_classification" name="consent_classification" defaultValue="programme_measurement">
                  <option value="programme_measurement">Programme measurement</option>
                  <option value="service_adaptation">Service adaptation</option>
                  <option value="operational">Operational</option>
                </select>
              </div>
              <fieldset className="field">
                <legend>Channels</legend>
                {instruments.channels.map((channel) => (
                  <label key={channel}><input type="checkbox" name="channels" value={channel} defaultChecked={["app","web"].includes(channel)} /> {humanise(channel)}</label>
                ))}
              </fieldset>
              <fieldset className="field">
                <legend>Supported locales</legend>
                {instruments.locales.map((locale) => (
                  <label key={locale}><input type="checkbox" name="supported_locales" value={locale} defaultChecked={locale === "en"} /> {locale}</label>
                ))}
              </fieldset>
              <input type="hidden" name="default_locale" value="en" />
              <input type="hidden" name="version" value="1.0" />
              <div className="field">
                <label>Schedule stage / offset days</label>
                <input name="schedule_stage" defaultValue="baseline" />
                <input name="schedule_offset_days" type="number" defaultValue="0" min="0" />
                <input name="schedule_stage" defaultValue="90_day" />
                <input name="schedule_offset_days" type="number" defaultValue="90" min="0" />
                <input name="schedule_stage" defaultValue="6_month" />
                <input name="schedule_offset_days" type="number" defaultValue="180" min="0" />
                <input name="schedule_stage" defaultValue="12_month" />
                <input name="schedule_offset_days" type="number" defaultValue="365" min="0" />
              </div>
              <div className="field">
                <label htmlFor="status">Status</label>
                <select id="status" name="status" defaultValue="draft"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option></select>
              </div>
              <button className="button" type="submit">Create instrument</button>
            </form>
          </section>
        ) : null}

        {selected ? (
          <section className="panel">
            <p className="eyebrow">MEL EXPORTS</p>
            <h2>Aggregate programme report pack</h2>
            <div className="case-list">
              <a className="button secondary" href={"/api/programme-export/" + selected.id + "/csv"}>CSV</a>
              <a className="button secondary" href={"/api/programme-export/" + selected.id + "/xlsx"}>XLSX</a>
              <a className="button secondary" href={"/api/programme-export/" + selected.id + "/zip"}>ZIP report pack</a>
            </div>
            <p className="muted">Individual participant records are not exported. Small cohorts remain suppressed.</p>
          </section>
        ) : null}

        <section className="panel">
          <p className="eyebrow">INSTRUMENT REGISTER</p>
          <h2>Configured check-ins</h2>
          {instruments.instruments.length === 0 ? (
            <StateNotice state="empty" message="No programme instruments configured in this scope." />
          ) : (
            <div className="case-list">
              {instruments.instruments.map((instrument) => (
                <article className="case-card" key={instrument.id}>
                  <div className="case-card-head">
                    <div>
                      <strong>{instrument.name}</strong>
                      <p className="muted">{instrument.code} · {humanise(instrument.outcome_domain)} · {instrument.version}</p>
                    </div>
                    <span className="badge">{instrument.status}</span>
                  </div>
                  <p className="muted">
                    Channels: {instrument.channels.join(", ")} · Locales: {instrument.supported_locales.join(", ")}
                  </p>
                  <p className="muted">
                    Questions: {instrument.questions.length} · Credit decision eligible: no
                  </p>
                </article>
              ))}
            </div>
          )}
        </section>

        {selected && instruments.instruments.length > 0 ? (
          <div className="grid grid-2 compass-grid">
            <section className="panel">
              <p className="eyebrow">QUESTION DESIGN</p>
              <h2>Add a question</h2>
              <form action={addProgrammeQuestionAction} className="form-grid">
                <input type="hidden" name="programme_id" value={selected.id} />
                <div className="field">
                  <label htmlFor="instrument_id">Instrument</label>
                  <select id="instrument_id" name="instrument_id">
                    {instruments.instruments.map((instrument) => <option value={instrument.id} key={instrument.id}>{instrument.name}</option>)}
                  </select>
                </div>
                <div className="field"><label htmlFor="question_code">Question code</label><input id="question_code" name="question_code" required /></div>
                <div className="field"><label htmlFor="prompt">English prompt</label><textarea id="prompt" name="prompt" rows={3} required /></div>
                <div className="field"><label htmlFor="help_text">Help text</label><textarea id="help_text" name="help_text" rows={2} /></div>
                <div className="field">
                  <label htmlFor="answer_type">Answer type</label>
                  <select id="answer_type" name="answer_type">
                    {instruments.answer_types.map((type) => <option value={type} key={type}>{humanise(type)}</option>)}
                  </select>
                </div>
                <div className="field"><label htmlFor="options">Choices, one per line</label><textarea id="options" name="options" rows={4} /></div>
                <div className="field"><label htmlFor="indicator_definition_id">Indicator ID</label><input id="indicator_definition_id" name="indicator_definition_id" inputMode="numeric" /></div>
                <div className="field"><label htmlFor="sort_order">Order</label><input id="sort_order" name="sort_order" type="number" defaultValue="0" /></div>
                <div className="field"><label htmlFor="min">Minimum</label><input id="min" name="min" inputMode="decimal" /></div>
                <div className="field"><label htmlFor="max">Maximum</label><input id="max" name="max" inputMode="decimal" /></div>
                <div className="field"><label htmlFor="unit">Unit</label><input id="unit" name="unit" /></div>
                <input type="hidden" name="verification_source" value="self_reported" />
                <label><input type="checkbox" name="required" /> Required</label>
                <button className="button" type="submit">Add question</button>
              </form>
            </section>

            <section className="panel">
              <p className="eyebrow">LOCALISATION</p>
              <h2>Add reviewed translation</h2>
              <p className="muted">Untranslated content falls back to English. OpFin does not machine-invent programme translations.</p>
              <form action={addQuestionTranslationAction} className="form-grid">
                <input type="hidden" name="programme_id" value={selected.id} />
                <div className="field">
                  <label htmlFor="question_id">Question</label>
                  <select id="question_id" name="question_id">
                    {instruments.instruments.flatMap((instrument) =>
                      instrument.questions.map((question) => (
                        <option value={question.id} key={question.id}>{instrument.code} · {question.code}</option>
                      ))
                    )}
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="locale">Locale</label>
                  <select id="locale" name="locale">
                    {instruments.locales.filter((locale) => locale !== "en").map((locale) => <option value={locale} key={locale}>{locale}</option>)}
                  </select>
                </div>
                <div className="field"><label htmlFor="translated_prompt">Reviewed prompt</label><textarea id="translated_prompt" name="translated_prompt" rows={3} required /></div>
                <div className="field"><label htmlFor="translated_help_text">Reviewed help text</label><textarea id="translated_help_text" name="translated_help_text" rows={2} /></div>
                <div className="field"><label htmlFor="translated_options">Reviewed choices, one per line</label><textarea id="translated_options" name="translated_options" rows={4} /></div>
                <button className="button" type="submit">Save translation</button>
              </form>
            </section>
          </div>
        ) : null}

        {selected ? (
          <div className="grid grid-2 compass-grid">
            <section className="panel">
              <p className="eyebrow">PARTNER USERS</p>
              <h2>Invite a dedicated programme partner</h2>
              <form action={inviteProgrammePartnerAction} className="form-grid">
                <input type="hidden" name="programme_id" value={selected.id} />
                <div className="field"><label htmlFor="partner_id">Configured partner ID</label><input id="partner_id" name="partner_id" inputMode="numeric" defaultValue={selected.partner_id ?? ""} required /></div>
                <div className="field"><label htmlFor="invited_name">Name</label><input id="invited_name" name="invited_name" required /></div>
                <div className="field"><label htmlFor="invited_phone">Phone</label><input id="invited_phone" name="invited_phone" /></div>
                <div className="field"><label htmlFor="invited_email">Email</label><input id="invited_email" name="invited_email" type="email" /></div>
                <div className="field">
                  <label htmlFor="access_level">Access level</label>
                  <select id="access_level" name="access_level" defaultValue="read_only">
                    <option value="read_only">Read only</option>
                    <option value="auditor">Auditor</option>
                    <option value="mel_officer">MEL officer</option>
                    <option value="programme_admin">Programme admin</option>
                  </select>
                </div>
                <button className="button" type="submit">Create invitation</button>
              </form>
            </section>

            <section className="panel">
              <p className="eyebrow">PROVIDER ADAPTERS</p>
              <h2>Configure a governed adapter</h2>
              <p className="muted">{adapters.activation_rule}</p>
              <form action={configureProviderAdapterAction} className="form-grid">
                <input type="hidden" name="programme_id" value={selected.id} />
                <div className="field"><label htmlFor="adapter_code">Code</label><input id="adapter_code" name="adapter_code" required /></div>
                <div className="field"><label htmlFor="adapter_name">Name</label><input id="adapter_name" name="adapter_name" required /></div>
                <div className="field">
                  <label htmlFor="adapter_type">Type</label>
                  <select id="adapter_type" name="adapter_type">{adapters.adapter_types.map((type) => <option value={type} key={type}>{humanise(type)}</option>)}</select>
                </div>
                <div className="field">
                  <label htmlFor="purpose">Purpose</label>
                  <select id="purpose" name="purpose">{adapters.purposes.map((purpose) => <option value={purpose} key={purpose}>{humanise(purpose)}</option>)}</select>
                </div>
                <div className="field"><label htmlFor="partner_id_adapter">Partner ID</label><input id="partner_id_adapter" name="partner_id" inputMode="numeric" defaultValue={selected.partner_id ?? ""} /></div>
                <div className="field"><label htmlFor="allowed_signal_keys">Allowed signal keys, one per line</label><textarea id="allowed_signal_keys" name="allowed_signal_keys" rows={4} required /></div>
                <div className="field"><label htmlFor="signal_mapping">Mapping source=target, one per line</label><textarea id="signal_mapping" name="signal_mapping" rows={4} /></div>
                <div className="field">
                  <label htmlFor="adapter_status">Status</label>
                  <select id="adapter_status" name="adapter_status" defaultValue="draft"><option value="draft">Draft</option><option value="ready">Ready</option><option value="active">Active</option><option value="disabled">Disabled</option></select>
                </div>
                <label><input type="checkbox" name="requires_credit_processing_consent" /> Requires credit-processing consent</label>
                <label><input type="checkbox" name="credentials_configured" /> Real credentials/configuration confirmed externally</label>
                <label><input type="checkbox" name="legal_basis_confirmed" /> Legal basis / data terms confirmed</label>
                <div className="field"><label htmlFor="activation_notes">Activation notes</label><textarea id="activation_notes" name="activation_notes" rows={3} /></div>
                <button className="button" type="submit">Save adapter</button>
              </form>
            </section>
          </div>
        ) : null}

        {selected ? (
          <section className="panel">
            <p className="eyebrow">PARTNER USER REGISTER</p>
            <h2>Programme-scoped access</h2>
            <p className="muted">{partnerUsers.access_boundary}</p>
            {partnerUsers.invitations.length > 0 ? (
              <div className="case-list">
                {partnerUsers.invitations.map((invitation) => (
                  <article className="case-card" key={"invite:" + invitation.id}>
                    <div className="case-card-head">
                      <div>
                        <strong>{invitation.invited_name}</strong>
                        <p className="muted">Invitation · {humanise(invitation.access_level)}</p>
                      </div>
                      <span className="badge">{invitation.status}</span>
                    </div>
                    {invitation.activation_token ? (
                      <>
                        <p className="muted">
                          One-time activation token. Deliver it through an approved secure channel. It is stored encrypted at rest and disappears after acceptance or expiry.
                        </p>
                        <code>{invitation.activation_token}</code>
                      </>
                    ) : null}
                  </article>
                ))}
              </div>
            ) : null}
            {partnerUsers.users.length === 0 ? (
              <p className="muted">No active or historical programme-partner accounts are registered yet.</p>
            ) : (
              <div className="case-list">
                {partnerUsers.users.map((user) => (
                  <article className="case-card" key={"partner:" + user.user_id}>
                    <div className="case-card-head">
                      <div>
                        <strong>{user.name}</strong>
                        <p className="muted">{humanise(user.access_level)} · {user.phone}</p>
                      </div>
                      <span className="badge">{user.status}</span>
                    </div>
                    {user.status === "active" ? (
                      <form action={revokeProgrammePartnerAccessAction}>
                        <input type="hidden" name="programme_id" value={selected.id} />
                        <input type="hidden" name="user_id" value={user.user_id} />
                        <button className="button secondary" type="submit">Revoke access</button>
                      </form>
                    ) : null}
                  </article>
                ))}
              </div>
            )}
          </section>
        ) : null}

        <section className="panel">
          <p className="eyebrow">FOLLOW-UP QUEUE</p>
          <h2>Due, overdue and completed measurements</h2>
          {operations.follow_ups.length === 0 ? (
            <StateNotice state="empty" message="No programme follow-ups in this scope." />
          ) : (
            <div className="case-list">
              {operations.follow_ups.map((item) => (
                <article className="case-card" key={item.id}>
                  <div className="case-card-head">
                    <div>
                      <strong>{item.instrument_name}</strong>
                      <p className="muted">User #{item.user_id} · {humanise(item.measurement_stage)} · Due {item.due_at}</p>
                    </div>
                    <span className="badge">{item.status}</span>
                  </div>
                  {item.status !== "completed" ? (
                    <Link
                      className="button secondary"
                      href={"/admin/inclusion/assisted?instrument_id=" +
                        item.instrument_id +
                        "&schedule_id=" + item.id +
                        "&user_id=" + item.user_id +
                        "&programme_id=" + item.programme_id}
                    >
                      Assisted capture
                    </Link>
                  ) : null}
                </article>
              ))}
            </div>
          )}
        </section>

        {selected && adapters.adapters.length > 0 ? (
          <section className="panel">
            <p className="eyebrow">PROVIDER EVIDENCE</p>
            <h2>Ingest an authorised provider payload</h2>
            <p className="muted">
              Use this only for evidence received through an approved provider process. Signal keys must match
              the adapter allow-list. Ingestion verifies provenance but never marks a signal risk-eligible.
            </p>
            <form action={ingestProviderEvidenceAction} className="form-grid">
              <input type="hidden" name="programme_id" value={selected.id} />
              <div className="field">
                <label htmlFor="adapter_id_ingest">Adapter</label>
                <select id="adapter_id_ingest" name="adapter_id">
                  {adapters.adapters.map((adapter) => (
                    <option value={adapter.id} key={adapter.id}>
                      {adapter.name} · {adapter.status}
                    </option>
                  ))}
                </select>
              </div>
              <div className="field"><label htmlFor="user_id_ingest">Subject user ID</label><input id="user_id_ingest" name="user_id" type="number" min="1" required /></div>
              <div className="field"><label htmlFor="provider_reference">Provider reference</label><input id="provider_reference" name="provider_reference" required /></div>
              <div className="field">
                <label htmlFor="signals">Signals, one key=value per line</label>
                <textarea id="signals" name="signals" rows={6} placeholder={'verified_monthly_income_minor=1500000\nbusiness_monthly_sales_minor=2500000'} required />
              </div>
              <div className="field"><label htmlFor="observed_at_ingest">Observed at</label><input id="observed_at_ingest" name="observed_at" type="datetime-local" /></div>
              <div className="field"><label htmlFor="expires_at_ingest">Expires at</label><input id="expires_at_ingest" name="expires_at" type="datetime-local" /></div>
              <button className="button" type="submit">Ingest provider evidence</button>
            </form>
          </section>
        ) : null}

        <section className="panel">
          <h2>Configured provider adapters</h2>
          {adapters.adapters.length === 0 ? (
            <p className="muted">No provider adapter configured in this programme scope.</p>
          ) : (
            <div className="case-list">
              {adapters.adapters.map((adapter) => (
                <article className="case-card" key={adapter.id}>
                  <div className="case-card-head">
                    <div><strong>{adapter.name}</strong><p className="muted">{adapter.code} · {humanise(adapter.adapter_type)}</p></div>
                    <span className="badge">{adapter.status}</span>
                  </div>
                  <p className="muted">Signals: {adapter.allowed_signal_keys.join(", ")}</p>
                  <p className="muted">Credentials confirmed: {adapter.credentials_configured ? "yes" : "no"} · Legal basis: {adapter.legal_basis_confirmed ? "yes" : "no"} · Stored secrets here: no</p>
                </article>
              ))}
            </div>
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen title="Programme delivery" description="Programme operations and delivery controls.">
        <StateNotice state="server" message={error instanceof Error ? error.message : "Programme delivery could not be loaded."} />
      </Screen>
    );
  }
}
