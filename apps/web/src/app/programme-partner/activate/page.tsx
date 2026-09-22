import {
  activateProgrammePartnerAction,
  requestProgrammePartnerOtpAction,
  verifyProgrammePartnerOtpAction
} from "@/app/programme-partner-actions";
import { StateNotice } from "@/components/Screen";

export default async function ProgrammePartnerActivatePage({
  searchParams
}: {
  searchParams?: Promise<{ step?: string; error?: string }>;
}) {
  const params = await searchParams;
  const step = params?.step ?? "request";

  return (
    <main className="auth-shell">
      <section className="auth-card">
        <div className="auth-brand">
          <strong>OpFin</strong>
          <p>Programme partner activation</p>
        </div>

        {params?.error ? <StateNotice state="validation" message={params.error} /> : null}

        {step === "request" ? (
          <>
            <h1>Verify your invitation</h1>
            <p>
              Use the one-time invitation token provided by the authorised programme operator.
              Your phone will then be verified with an OpFin OTP.
            </p>
            <form action={requestProgrammePartnerOtpAction} className="form-grid">
              <div className="field">
                <label htmlFor="invitation_token">Invitation token</label>
                <input id="invitation_token" name="invitation_token" autoComplete="off" required />
              </div>
              <div className="field">
                <label htmlFor="phone">Invited phone number</label>
                <input id="phone" name="phone" inputMode="tel" autoComplete="tel" required />
              </div>
              <button className="button" type="submit">Send verification code</button>
            </form>
          </>
        ) : null}

        {step === "verify" ? (
          <>
            <h1>Enter the verification code</h1>
            <p>The six-digit code expires after a short period. Do not share it with programme staff.</p>
            <form action={verifyProgrammePartnerOtpAction} className="form-grid">
              <div className="field">
                <label htmlFor="otp">6-digit OTP</label>
                <input id="otp" name="otp" inputMode="numeric" autoComplete="one-time-code" maxLength={6} required />
              </div>
              <button className="button" type="submit">Verify phone</button>
            </form>
          </>
        ) : null}

        {step === "activate" ? (
          <>
            <h1>Create your programme-partner sign-in</h1>
            <p>
              This creates a dedicated programme-scoped account. It does not give access to any participant's
              personal financial account or individual programme records.
            </p>
            <form action={activateProgrammePartnerAction} className="form-grid">
              <div className="field"><label htmlFor="name">Full name</label><input id="name" name="name" required /></div>
              <div className="field"><label htmlFor="first_name">First name</label><input id="first_name" name="first_name" /></div>
              <div className="field"><label htmlFor="last_name">Last name</label><input id="last_name" name="last_name" /></div>
              <div className="field"><label htmlFor="email">Email</label><input id="email" name="email" type="email" /></div>
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
                <input id="pin" name="pin" type="password" inputMode="numeric" maxLength={6} autoComplete="new-password" required />
              </div>
              <div className="field">
                <label htmlFor="pin_confirmation">Confirm PIN</label>
                <input id="pin_confirmation" name="pin_confirmation" type="password" inputMode="numeric" maxLength={6} autoComplete="new-password" required />
              </div>
              <label>
                <input type="checkbox" name="terms_accepted" required /> I understand this account is programme-scoped, auditable and does not provide access to individual participant financial records.
              </label>
              <button className="button" type="submit">Activate programme access</button>
            </form>
          </>
        ) : null}
      </section>
    </main>
  );
}
