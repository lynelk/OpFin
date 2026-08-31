import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { financialWellbeingApi } from "@/lib/api/financial-wellbeing";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

type SetupStep = {
  title: string;
  text: string;
  href: string;
  action: string;
  complete: boolean;
  essential: boolean;
};

export default async function SetupPage() {
  const token = await getAccessToken();

  try {
    const [profileResponse, kycResponse, consentResponse, accountsResponse] = await Promise.all([
      opfinApi.profile(token),
      opfinApi.kycStatus(token),
      opfinApi.consents(token),
      financialWellbeingApi.accounts(token)
    ]);

    const user = profileResponse.data.user;
    const latestKyc = kycResponse.data.latest_case;
    const consents = consentResponse.data.consents;
    const hasCreditConsent = consents.some((consent) => consent.purpose === "credit_processing" && consent.status === "granted");
    const hasAccount = accountsResponse.data.accounts.some((account) => account.active);

    const steps: SetupStep[] = [
      {
        title: "Secure your account",
        text: "Your account is active. Keep your sign-in details private and review Security Centre controls from More.",
        href: "/more",
        action: "Review security",
        complete: Boolean(user.id),
        essential: true
      },
      {
        title: "Verify your identity",
        text: "Complete identity verification once so regulated products do not repeatedly ask for the same evidence.",
        href: "/kyc",
        action: latestKyc?.status === "verified" ? "View verification" : "Continue verification",
        complete: latestKyc?.status === "verified",
        essential: true
      },
      {
        title: "Build your money picture",
        text: "Add at least one financial account or balance source so Financial Compass can distinguish available money from commitments.",
        href: "/money",
        action: hasAccount ? "Review money picture" : "Add an account",
        complete: hasAccount,
        essential: true
      },
      {
        title: "Review data permissions",
        text: "Grant only the permissions a journey needs. Credit data permission remains optional until you choose to explore borrowing.",
        href: "/consent",
        action: "Review permissions",
        complete: hasCreditConsent,
        essential: false
      },
      {
        title: "Choose your next financial action",
        text: "Once the essentials are complete, OpFin can prioritise a practical next step across managing, saving, borrowing, protecting and growing.",
        href: "/dashboard",
        action: "Open Financial Compass",
        complete: latestKyc?.status === "verified" && hasAccount,
        essential: false
      }
    ];

    const essentialSteps = steps.filter((step) => step.essential);
    const essentialComplete = essentialSteps.filter((step) => step.complete).length;
    const nextStep = steps.find((step) => !step.complete) ?? steps[steps.length - 1];
    const percent = Math.round((essentialComplete / essentialSteps.length) * 100);

    return (
      <Screen
        title="Your OpFin setup"
        description="Complete essentials once, then provide additional information only when a product or service genuinely needs it."
      >
        <section className="panel compass-next-action">
          <p className="eyebrow">Activation progress</p>
          <div className="case-card-head">
            <div>
              <h2>{essentialComplete} of {essentialSteps.length} essentials complete</h2>
              <p className="muted">Your progress is linked to your account so you can leave and continue later.</p>
            </div>
            <span className="badge ok">{percent}%</span>
          </div>
          <div className="setup-progress" aria-label={`${percent}% setup complete`}><span style={{ width: `${percent}%` }} /></div>
          {!nextStep.complete ? <Link className="button compass-action" href={nextStep.href}>{nextStep.action}</Link> : null}
        </section>

        <section className="panel">
          <h2>Setup checklist</h2>
          <div className="case-list">
            {steps.map((step, index) => (
              <article className="case-card setup-step" key={step.title}>
                <div className="case-card-head">
                  <div>
                    <p className="eyebrow">{step.essential ? `ESSENTIAL ${index + 1}` : "WHEN RELEVANT"}</p>
                    <h3>{step.title}</h3>
                  </div>
                  <span className={`badge ${step.complete ? "ok" : "warn"}`}>{step.complete ? "Complete" : "To do"}</span>
                </div>
                <p className="muted">{step.text}</p>
                <Link className={`button ${step.complete ? "secondary" : ""}`} href={step.href}>{step.action}</Link>
              </article>
            ))}
          </div>
        </section>

        <section className="panel">
          <h2>Why OpFin asks for information</h2>
          <div className="grid grid-3">
            <div><strong>Identity</strong><p className="muted">Needed to protect your account and meet regulated product requirements.</p></div>
            <div><strong>Financial position</strong><p className="muted">Used to provide practical money guidance without pretending estimates are confirmed cash.</p></div>
            <div><strong>Permissions</strong><p className="muted">Used only for the purpose and period you approve, with revocation available in the Consent Centre.</p></div>
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
