import Link from "next/link";
import { cookies } from "next/headers";
import { resetPasswordAction } from "@/app/credential-actions";
import { AuthShell } from "@/components/AuthShell";

export default async function ResetPasswordPage({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string }>;
}) {
  const params = await searchParams;
  const remembered = (await cookies()).has("opfin_reset_identifier");

  return (
    <AuthShell
      eyebrow="Account recovery"
      title="Enter your code"
      description="If an account matches what you entered, a 6-digit code is on its way. It expires in 5 minutes and works once."
      footer={<div className="auth-help-row"><Link href="/forgot-password">Send a new code</Link><Link href="/login">Back to sign in</Link></div>}
    >
      {params?.message ? (
        <div className="placeholder state-validation auth-notice" role="alert">
          <strong>Password not reset</strong>
          <p>{params.message}</p>
        </div>
      ) : null}

      <form action={resetPasswordAction} className="form-grid">
        {!remembered ? (
          <div className="field">
            <label htmlFor="identifier">Staff email or phone number</label>
            <input id="identifier" name="identifier" autoComplete="username" required />
          </div>
        ) : null}
        <div className="field">
          <label htmlFor="code">6-digit code</label>
          <input id="code" name="code" inputMode="numeric" autoComplete="one-time-code" pattern="\d{6}" maxLength={6} required />
        </div>
        <div className="field">
          <label htmlFor="password">New password</label>
          <input id="password" name="password" type="password" autoComplete="new-password" minLength={12} aria-describedby="password-rule" required />
          <span id="password-rule">At least 12 characters, with upper- and lower-case letters, a number and a symbol.</span>
        </div>
        <div className="field">
          <label htmlFor="password_confirmation">Type the new password again</label>
          <input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" minLength={12} required />
        </div>
        <button className="button auth-primary-action" type="submit">Reset password</button>
      </form>
    </AuthShell>
  );
}
