"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { startSignup, verifySignup, finishSignup } from "../signup-actions";

type Step = "phone" | "code" | "profile";

export default function SignupPage() {
  const [step, setStep] = useState<Step>("phone");
  const [phone, setPhone] = useState("");
  const [accountType, setAccountType] = useState<"individual" | "organisation">("individual");
  const [code, setCode] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState(false);
  const [pending, startTransition] = useTransition();

  function sendCode() {
    startTransition(async () => {
      const result = await startSignup(phone);
      setMessage(result.message);
      setError(!result.ok);
      if (result.ok) setStep("code");
    });
  }
  function verifyCode() {
    startTransition(async () => {
      const result = await verifySignup(phone, code);
      setMessage(result.message);
      setError(!result.ok);
      if (result.ok) setStep("profile");
    });
  }
  function register(data: FormData) {
    startTransition(async () => {
      const result = await finishSignup(data);
      if (result) {
        setMessage(result.message);
        setError(!result.ok);
      }
    });
  }

  return (
    <main className="auth-shell">
      <div className="auth-layout">
        <aside className="auth-story">
          <Link className="auth-story-brand" href="/">OpFin</Link>
          <div className="auth-story-copy">
            <p className="auth-kicker">YOUR FINANCIAL JOURNEY</p>
            <h2>Start with your phone. Move at your pace.</h2>
            <p>Create one secure identity for personal finance and the Financial Spaces you are authorised to join.</p>
          </div>
        </aside>
        <section className="auth-panel">
          <p className="auth-eyebrow">Create your account · {step === "phone" ? "1" : step === "code" ? "2" : "3"} of 3</p>
          <h1>{step === "phone" ? "Get started with OpFin" : step === "code" ? "Verify your phone" : "Complete your profile"}</h1>
          <p className="auth-lead">You can explore your account after registration. Financial services may require additional identity and eligibility checks.</p>
          {message && <div className={"placeholder state-" + (error ? "server" : "success") + " auth-notice"} role={error ? "alert" : "status"}>{message}</div>}
          {step === "phone" && (
            <form className="form-grid" onSubmit={e => { e.preventDefault(); sendCode(); }}>
              <div className="field">
                <label htmlFor="account_type">I am joining as</label>
                <select id="account_type" value={accountType} onChange={e => setAccountType(e.target.value as typeof accountType)}>
                  <option value="individual">An individual</option>
                  <option value="organisation">An organisation or group representative</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="phone">Ugandan mobile number</label>
                <input id="phone" type="tel" autoComplete="tel" inputMode="tel" required placeholder="07XXXXXXXX" value={phone} onChange={e => setPhone(e.target.value)} />
              </div>
              {accountType === "organisation" && <p className="auth-note">First verify your personal identity. You can create or join an organisation through an authorised Financial Space after registration. Choosing this option does not grant administrator access.</p>}
              <button className="button auth-primary-action" type="submit" disabled={pending}>{pending ? "Sending code…" : "Send verification code"}</button>
            </form>
          )}
          {step === "code" && (
            <form className="form-grid" onSubmit={e => { e.preventDefault(); verifyCode(); }}>
              <p>Enter the six-digit code sent to {phone}.</p>
              <div className="field">
                <label htmlFor="code">Verification code</label>
                <input id="code" value={code} onChange={e => setCode(e.target.value)} inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} required />
              </div>
              <button className="button auth-primary-action" disabled={pending} type="submit">{pending ? "Verifying…" : "Verify phone"}</button>
              <button type="button" className="button secondary" disabled={pending} onClick={sendCode}>Send another code</button>
              <button type="button" className="button secondary" onClick={() => { setMessage(""); setStep("phone"); }}>Change phone</button>
            </form>
          )}
          {step === "profile" && (
            <form className="form-grid" action={register}>
              <div className="field"><label htmlFor="first_name">First name</label><input id="first_name" name="first_name" autoComplete="given-name" maxLength={80} required /></div>
              <div className="field"><label htmlFor="last_name">Last name</label><input id="last_name" name="last_name" autoComplete="family-name" maxLength={80} required /></div>
              <div className="field"><label htmlFor="pin">Create a six-digit PIN</label><input id="pin" name="pin" type="password" autoComplete="new-password" inputMode="numeric" pattern="[0-9]{6}" maxLength={6} required /></div>
              <div className="field"><label htmlFor="pin_confirmation">Confirm PIN</label><input id="pin_confirmation" name="pin_confirmation" type="password" autoComplete="new-password" inputMode="numeric" pattern="[0-9]{6}" maxLength={6} required /></div>
              <label><input name="terms_accepted" type="checkbox" value="yes" required /> I accept the OpFin Terms of Service and Privacy Notice. Credit-information consent is requested separately.</label>
              <button className="button auth-primary-action" disabled={pending} type="submit">{pending ? "Creating account…" : "Create account"}</button>
            </form>
          )}
          <div className="auth-panel-footer"><Link href="/login">Already registered? Sign in</Link></div>
        </section>
      </div>
    </main>
  );
}
