import Link from "next/link";
import { revalidatePath } from "next/cache";
import { Screen, StateNotice } from "@/components/Screen";
import { experienceApi } from "@/lib/api/experience";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

const goalOptions = [
  { code: "control_spending", label: "Control spending" },
  { code: "build_emergency_fund", label: "Build emergency savings" },
  { code: "borrow_responsibly", label: "Borrow responsibly" },
  { code: "protect_family", label: "Protect my family" },
  { code: "grow_money", label: "Grow my money" }
];

const stepContent: Record<string, { title: string; text: string; href: string; action: string }> = {
  secure_account: {
    title: "Secure your account",
    text: "Verify your phone and keep control of account security before using financial services.",
    href: "/more",
    action: "Review security"
  },
  verify_identity: {
    title: "Verify your identity",
    text: "Complete identity verification once so regulated products do not repeatedly request the same evidence.",
    href: "/kyc",
    action: "Continue verification"
  },
  build_money_picture: {
    title: "Build your money picture",
    text: "Add at least one account or balance source so Financial Compass can separate available money from commitments.",
    href: "/money",
    action: "Open money plan"
  },
  choose_primary_goal: {
    title: "Choose your main goal",
    text: "Tell OpFin what matters most now. This guides prioritisation rather than restricting other services.",
    href: "/setup",
    action: "Choose below"
  },
  review_permissions: {
    title: "Review data permissions",
    text: "Grant only the permissions a journey genuinely needs, and revoke them from the Consent Centre where permitted.",
    href: "/consent",
    action: "Review permissions"
  }
};

async function savePrimaryGoal(formData: FormData) {
  "use server";
  const token = await getAccessToken();
  const goal = String(formData.get("goal") ?? "");
  await experienceApi.saveActivation({ primary_financial_goal: goal }, token);
  revalidatePath("/setup");
  revalidatePath("/dashboard");
}

async function saveNotifications(formData: FormData) {
  "use server";
  const token = await getAccessToken();
  const enabled = String(formData.get("enabled") ?? "false") === "true";
  await experienceApi.saveActivation({ notifications_enabled: enabled }, token);
  revalidatePath("/setup");
}

export default async function SetupPage() {
  const token = await getAccessToken();

  try {
    const activation = await experienceApi.activation(token);
    const nextStep = activation.steps.find((step) => !step.complete);
    const nextContent = nextStep ? stepContent[nextStep.code] : null;
    const currentGoal = activation.profile?.primary_financial_goal ?? null;
    const notificationsEnabled = Boolean(activation.profile?.notifications_enabled);

    return (
      <Screen
        title="Your OpFin setup"
        description="Complete essentials once, then provide extra information only when a product or service genuinely needs it."
      >
        <section className="panel compass-next-action">
          <p className="eyebrow">Activation progress</p>
          <div className="case-card-head">
            <div>
              <h2>{activation.essential_complete} of {activation.essential_total} essentials complete</h2>
              <p className="muted">Progress is stored with your account so you can continue safely from another session or device.</p>
            </div>
            <span className="badge ok">{activation.activation_percent}%</span>
          </div>
          <div className="setup-progress" aria-label={`${activation.activation_percent}% setup complete`}>
            <span style={{ width: `${activation.activation_percent}%` }} />
          </div>
          {nextContent && nextStep?.code !== "choose_primary_goal" ? (
            <Link className="button compass-action" href={nextContent.href}>{nextContent.action}</Link>
          ) : activation.activation_complete ? (
            <Link className="button compass-action" href="/dashboard">Open Financial Compass</Link>
          ) : null}
        </section>

        <section className="panel">
          <h2>Setup checklist</h2>
          <div className="case-list">
            {activation.steps.map((step, index) => {
              const content = stepContent[step.code];
              return (
                <article className="case-card setup-step" key={step.code}>
                  <div className="case-card-head">
                    <div>
                      <p className="eyebrow">{step.essential ? `ESSENTIAL ${index + 1}` : "WHEN RELEVANT"}</p>
                      <h3>{content?.title ?? step.code.replaceAll("_", " ")}</h3>
                    </div>
                    <span className={`badge ${step.complete ? "ok" : "warn"}`}>{step.complete ? "Complete" : "To do"}</span>
                  </div>
                  <p className="muted">{content?.text}</p>
                  {content && step.code !== "choose_primary_goal" ? (
                    <Link className={`button ${step.complete ? "secondary" : ""}`} href={content.href}>{content.action}</Link>
                  ) : null}
                </article>
              );
            })}
          </div>
        </section>

        <section className="panel">
          <p className="eyebrow">PERSONALISE THE JOURNEY</p>
          <h2>What matters most right now?</h2>
          <p className="muted">This does not change eligibility or hide other services. It simply helps Financial Compass prioritise the most useful next action.</p>
          <div className="goal-choice-grid">
            {goalOptions.map((goal) => (
              <form action={savePrimaryGoal} key={goal.code}>
                <input type="hidden" name="goal" value={goal.code} />
                <button className={`goal-choice ${currentGoal === goal.code ? "selected" : ""}`} type="submit">
                  <span>{goal.label}</span>
                  <small>{currentGoal === goal.code ? "Current priority" : "Set as priority"}</small>
                </button>
              </form>
            ))}
          </div>
        </section>

        <section className="panel">
          <div className="case-card-head">
            <div>
              <h2>Useful reminders</h2>
              <p className="muted">Control whether OpFin may send non-essential progress reminders. Transactional and legally required notices remain separate.</p>
            </div>
            <form action={saveNotifications}>
              <input type="hidden" name="enabled" value={notificationsEnabled ? "false" : "true"} />
              <button className={`button ${notificationsEnabled ? "secondary" : ""}`} type="submit">
                {notificationsEnabled ? "Turn off reminders" : "Enable reminders"}
              </button>
            </form>
          </div>
        </section>

        <section className="panel">
          <h2>Why OpFin asks for information</h2>
          <div className="grid grid-3">
            <div><strong>Identity</strong><p className="muted">Needed to protect your account and meet regulated product requirements.</p></div>
            <div><strong>Financial position</strong><p className="muted">Used for practical money guidance while keeping estimates distinct from confirmed cash.</p></div>
            <div><strong>Permissions</strong><p className="muted">Used only for the purpose and period you approve, with revocation available from the Consent Centre.</p></div>
          </div>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load your setup progress.";
    return (
      <Screen title="Your OpFin setup" description="Resume your activation journey from one place.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
