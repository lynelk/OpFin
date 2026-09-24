import Link from "next/link";
import {
  activateProgrammePartnerAction,
  requestProgrammePartnerOtpAction,
  verifyProgrammePartnerOtpAction
} from "@/app/programme-partner-actions";
import { AuthShell } from "@/components/AuthShell";
import { StateNotice } from "@/components/Screen";

const stepCopy: Record<string, { title: string; description: string }> = {
  request: {
    title: "Verify your programme invitation",
    description: "Use the invitation token issued by the authorised programme operator, then verify the invited phone number."
  },
  verify: {
    title: "Enter the verification code",
    description: "Enter the six-digit code sent to the invited phone number. Keep the code private, including from programme staff."
  },
  activate: {
    title: "Create your programme-partner sign-in",
    description: "Finish your programme-scoped account. This access does not expose participants' private Personal Space records."
  }
};

export default async function ProgrammePartnerActivatePage({
  searchParams
}: {
  searchParams?: Promise<{ step?: string; error?: string }>;
}) {
  const params = await searchParams;
  const step = params?.step ?? "request";
  const copy = stepCopy[step] ?? stepCopy.request;

  return (
    <AuthShell
      eyebrow="Programme Partner Workspace"
      title={copy.title}
      description={copy.description}
      footer={
        <div className="auth-help-row">
          <Link href="/login?context=partner">Already activated? Sign in</Link>
          <Link href="/">Back to OpFin</Link>
        </div>
      }
    >
      {params?.error ? <StateNotice state="validation" message={params.error} /> : null}

      {step === "request" ? (
        <form action={requestProgrammePartnerOtpAction} className="form-grid">
          <div className="field">
            <label htmlFor="invitation_token">Invitation token</label>
            <input id="invitation_token" name="invitation_token" autoComplete="off" required />
          </div>
          <div className="field">
            <label htmlFor="phone">Invited phone number</label>
            <input id="phone" name="phone" inputMode="tel" autoComplete="tel" required />
          </div>
          <button className="button auth-primary-action" type="submit">Send verification code</button>
        </form>
      ) : null}

      {step === "verify" ? (
        <form action={verifyProgrammePartnerOtpAction} className="form-grid">
          <div className="field">
            <label htmlFor="otp">6-digit verification code</label>
            <input
              id="otp"
              name="otp"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              pattern="[0-9]{6}"
              minLength={6}
              maxLength={6}
              required
            />
          </div>
          <button className="button auth-primary-action" type="submit">Verify phone</button>
        </form>
      ) : null}

      {step === "activate" ? (
        <form action={activateProgrammePartnerAction} className="form-grid">
          <div className="field"><label htmlFor="name">Full name</label><input id="name" name="name" autoComplete="name" required /></div>
          <div className="field"><label htmlFor="first_name">First name</label><input id="first_name" name="first_name" autoComplete="given-name" /></div>
          <div className="field"><label htmlFor="last_name">Last name</label><input id="last_name" name="last_name" autoComplete="family-name" /></div>
          <div className="field"><label htmlFor="email">Email</label><input id="email" name="email" type="email" autoComplete="email" /></div>
          <div className="field">
            <label htmlFor="preferred_language">Preferred language</label>
            <select id="preferred_language" name="preferred_language" defaultValue="en">
              <option value="en">English</option>
              <option value="sw">Swahili</option>
              <option value="lg">Luganda</option>
              <option value="nyn-ruk">Runyankole-Rukiga</option>
              <option value="fr">French</option>
              <option value="ar">Arabic</option>
              <option value="ach">Acholi</option>
            </select>
          </div>
          <div className="field">
            <label htmlFor="pin">6-digit PIN</label>
            <input id="pin" name="pin" type="password" inputMode="numeric" pattern="[0-9]{6}" minLength={6} maxLength={6} autoComplete="new-password" required />
          </div>
          <div className="field">
            <label htmlFor="pin_confirmation">Confirm PIN</label>
            <input id="pin_confirmation" name="pin_confirmation" type="password" inputMode="numeric" pattern="[0-9]{6}" minLength={6} maxLength={6} autoComplete="new-password" required />
          </div>
          <label className="auth-consent">
            <input type="checkbox" name="terms_accepted" required />
            <span>I understand this account is programme-scoped, auditable and does not provide access to individual participant financial records.</span>
          </label>
          <button className="button auth-primary-action" type="submit">Activate programme access</button>
        </form>
      ) : null}
    </AuthShell>
  );
}
