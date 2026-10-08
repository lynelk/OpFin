import Link from "next/link";
import { logoutAction } from "@/app/actions";
import { changePasswordAction } from "@/app/credential-actions";
import { AuthShell } from "@/components/AuthShell";
import { getAccessToken } from "@/lib/auth/session";

export default async function ChangePasswordPage({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string }>;
}) {
  const params = await searchParams;
  const token = await getAccessToken();

  return (
    <AuthShell
      eyebrow="Account security"
      title="Choose a new password"
      description="Your current password was a one-time password, or it needs replacing. Choose a new one to continue."
      footer={
        <form action={logoutAction} className="auth-help-row">
          <button className="button secondary" type="submit">Sign out</button>
        </form>
      }
    >
      {!token ? (
        <div className="placeholder state-unauthorized auth-notice" role="alert">
          <strong>Your session has ended</strong>
          <p><Link href="/login">Sign in again</Link> to change your password.</p>
        </div>
      ) : null}
      {params?.message ? (
        <div className="placeholder state-validation auth-notice" role="alert">
          <strong>Password not changed</strong>
          <p>{params.message}</p>
        </div>
      ) : null}

      <form action={changePasswordAction} className="form-grid">
        <div className="field">
          <label htmlFor="current_password">Current password</label>
          <input id="current_password" name="current_password" type="password" autoComplete="current-password" required />
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
        <button className="button auth-primary-action" type="submit">Change password</button>
      </form>

      <p className="auth-note">Changing your password signs you out everywhere else.</p>
    </AuthShell>
  );
}
