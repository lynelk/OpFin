import { submitProgrammeCheckInAction } from "@/app/programme-completion-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { programmeCompletionApi, type ProgrammeQuestion } from "@/lib/api/programme-completion";
import { getAccessToken } from "@/lib/auth/session";

function QuestionField({ question }: Readonly<{ question: ProgrammeQuestion }>) {
  const name = "question_" + question.id;
  const label = question.required ? question.prompt + " *" : question.prompt;

  if (question.answer_type === "boolean") {
    return (
      <div className="field">
        <label htmlFor={name}>{label}</label>
        <select id={name} name={name} required={question.required} defaultValue="">
          <option value="">Choose</option>
          <option value="true">Yes</option>
          <option value="false">No</option>
        </select>
        {question.help_text ? <p className="muted">{question.help_text}</p> : null}
      </div>
    );
  }

  if (question.answer_type === "single_choice") {
    return (
      <div className="field">
        <label htmlFor={name}>{label}</label>
        <select id={name} name={name} required={question.required} defaultValue="">
          <option value="">Choose</option>
          {question.options.map((option) => (
            <option value={option} key={option}>{option}</option>
          ))}
        </select>
        {question.help_text ? <p className="muted">{question.help_text}</p> : null}
      </div>
    );
  }

  if (question.answer_type === "multi_choice") {
    return (
      <fieldset className="field">
        <legend>{label}</legend>
        {question.options.map((option) => (
          <label key={option}>
            <input type="checkbox" name={name} value={option} /> {option}
          </label>
        ))}
        {question.help_text ? <p className="muted">{question.help_text}</p> : null}
      </fieldset>
    );
  }

  const numeric = ["integer", "decimal", "currency_minor"].includes(question.answer_type);
  return (
    <div className="field">
      <label htmlFor={name}>{label}</label>
      <input
        id={name}
        name={name}
        required={question.required}
        inputMode={numeric ? "decimal" : undefined}
        type={numeric ? "number" : "text"}
        step={question.answer_type === "decimal" ? "any" : numeric ? "1" : undefined}
      />
      {question.help_text ? <p className="muted">{question.help_text}</p> : null}
    </div>
  );
}

export default async function ProgrammeCheckInsPage({
  searchParams
}: {
  searchParams?: Promise<{ status?: string; error?: string; message?: string }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();

  try {
    const data = await programmeCompletionApi.dueCheckIns(token);

    return (
      <Screen
        title="Programme check-ins"
        description="Short, consented programme follow-ups. Programme answers stay outside credit scoring."
      >
        {params?.status === "saved" ? (
          <StateNotice state="success" message="Programme check-in saved." />
        ) : null}
        {params?.message ? (
          <StateNotice
            state={params.error === "validation" ? "validation" : "server"}
            message={params.message}
          />
        ) : null}

        <section className="panel">
          <h2>How this works</h2>
          <p>
            OpFin only shows programme instruments that are currently due for your enrolled programmes.
            Questions use your reviewed language version where available; otherwise English is shown rather
            than an unverified automatic translation.
          </p>
          <p className="muted">{data.translation_policy}</p>
        </section>

        {data.instruments.length === 0 ? (
          <StateNotice state="empty" message="You have no programme check-in due right now." />
        ) : (
          data.instruments.map((instrument) => (
            <section className="panel" key={instrument.id}>
              <div className="case-card-head">
                <div>
                  <p className="eyebrow">{instrument.programme?.name ?? "PROGRAMME"}</p>
                  <h2>{instrument.name}</h2>
                  <p className="muted">
                    {instrument.schedule?.measurement_stage ?? instrument.default_measurement_stage}
                    {instrument.schedule?.due_at ? " · Due " + instrument.schedule.due_at : ""}
                  </p>
                </div>
                <span className="badge">{instrument.schedule?.status ?? "due"}</span>
              </div>
              {instrument.description ? <p>{instrument.description}</p> : null}
              <form action={submitProgrammeCheckInAction} className="form-grid">
                <input type="hidden" name="instrument_id" value={instrument.id} />
                <input type="hidden" name="schedule_id" value={instrument.schedule?.id ?? ""} />
                <input type="hidden" name="locale" value={data.locale} />
                <input
                  type="hidden"
                  name="question_ids"
                  value={instrument.questions.map((question) => question.id).join(",")}
                />
                {instrument.questions.map((question) => (
                  <div key={question.id}>
                    <input
                      type="hidden"
                      name={"question_type_" + question.id}
                      value={question.answer_type}
                    />
                    <QuestionField question={question} />
                    {question.translation_fallback ? (
                      <p className="muted">
                        English fallback shown because a reviewed translation for {question.requested_locale} is not available.
                      </p>
                    ) : null}
                  </div>
                ))}
                <button className="button" type="submit">Save check-in</button>
              </form>
              <p className="muted">
                These answers are programme-measurement evidence and are marked non-credit-eligible.
              </p>
            </section>
          ))
        )}
      </Screen>
    );
  } catch (error) {
    return (
      <Screen
        title="Programme check-ins"
        description="Short, consented programme follow-ups."
      >
        <StateNotice
          state="server"
          message={error instanceof Error ? error.message : "Programme check-ins could not be loaded."}
        />
      </Screen>
    );
  }
}
