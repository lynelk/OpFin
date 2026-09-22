import { submitAssistedProgrammeCheckInAction } from "@/app/programme-completion-actions";
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
      </div>
    );
  }

  if (question.answer_type === "single_choice") {
    return (
      <div className="field">
        <label htmlFor={name}>{label}</label>
        <select id={name} name={name} required={question.required} defaultValue="">
          <option value="">Choose</option>
          {question.options.map((option) => <option value={option} key={option}>{option}</option>)}
        </select>
      </div>
    );
  }

  if (question.answer_type === "multi_choice") {
    return (
      <fieldset className="field">
        <legend>{label}</legend>
        {question.options.map((option) => (
          <label key={option}><input type="checkbox" name={name} value={option} /> {option}</label>
        ))}
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
        type={numeric ? "number" : "text"}
        inputMode={numeric ? "decimal" : undefined}
        step={question.answer_type === "decimal" ? "any" : numeric ? "1" : undefined}
      />
      {question.help_text ? <p className="muted">{question.help_text}</p> : null}
    </div>
  );
}

export default async function AssistedProgrammeCapturePage({
  searchParams
}: {
  searchParams?: Promise<{
    instrument_id?: string;
    schedule_id?: string;
    user_id?: string;
    programme_id?: string;
    error?: string;
    message?: string;
  }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();
  const instrumentId = Number(params?.instrument_id);
  const programmeId = Number(params?.programme_id);
  const userId = Number(params?.user_id);

  try {
    const register = await programmeCompletionApi.instruments(
      Number.isInteger(programmeId) && programmeId > 0 ? programmeId : undefined,
      token
    );
    const instrument = register.instruments.find((item) => item.id === instrumentId);

    if (!instrument || !Number.isInteger(userId) || userId <= 0) {
      return (
        <Screen title="Assisted programme capture" description="Authorised staff capture with explicit actor separation.">
          <StateNotice state="validation" message="A valid programme instrument and participant are required." />
        </Screen>
      );
    }

    return (
      <Screen
        title="Assisted programme capture"
        description="Capture programme answers on behalf of a participant without impersonating their customer account."
      >
        {params?.message ? (
          <StateNotice
            state={params.error === "validation" ? "validation" : "server"}
            message={params.message}
          />
        ) : null}
        <section className="panel">
          <p className="eyebrow">ASSISTED CAPTURE</p>
          <h2>{instrument.name}</h2>
          <p>
            Participant user #{userId}. The signed-in operator is separately recorded as the capture actor.
            Programme measurement consent and active enrolment are still required.
          </p>
          <p className="muted">
            Assisted capture never changes the participant's credit score, price or limit.
          </p>
          <form action={submitAssistedProgrammeCheckInAction} className="form-grid">
            <input type="hidden" name="programme_id" value={programmeId} />
            <input type="hidden" name="instrument_id" value={instrument.id} />
            <input type="hidden" name="user_id" value={userId} />
            <input type="hidden" name="schedule_id" value={params?.schedule_id ?? ""} />
            <input type="hidden" name="locale" value="en" />
            <input
              type="hidden"
              name="question_ids"
              value={instrument.questions.map((question) => question.id).join(",")}
            />
            {instrument.questions.map((question) => (
              <div key={question.id}>
                <input type="hidden" name={"question_type_" + question.id} value={question.answer_type} />
                <QuestionField question={question} />
              </div>
            ))}
            <button className="button" type="submit">Save assisted check-in</button>
          </form>
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen title="Assisted programme capture" description="Authorised staff capture.">
        <StateNotice state="server" message={error instanceof Error ? error.message : "Assisted capture could not be loaded."} />
      </Screen>
    );
  }
}
