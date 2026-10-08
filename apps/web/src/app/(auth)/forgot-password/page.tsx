import Link from "next/link";
import { requestResetCodeAction } from "@/app/credential-actions";
import { AuthShell } from "@/components/AuthShell";

export default async function ForgotPasswordPage({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string }>;
}) {
  const params = await searchParams;

  return (
    <AuthShell
      eyebrow="Account recovery"
      title="Reset your password"
      description="Staff: enter your work email and we will email you a code. Or enter your phone number and we will text you a code."
      footer={<div className="auth-help-row"><Link href="/login">Back to sign in</Link></div>}
    >
      {params?.message ? (
        <div className="placeholder state-validation auth-notice" role="alert">
          <strong>Code not sent</strong>
          <p>{params.message}</p>
        </div>
      ) : null}

      <form action={requestResetCodeAction} className="form-grid">
        <div className="field">
          <label htmlFor="identifier">Staff email or phone number</label>
          <input id="identifier" name="identifier" autoComplete="username" required />
        </div>
        <button className="button auth-primary-action" type="submit">Send code</button>
      </form>

      <p className="auth-note">Customers reset their PIN in the OpFin app. A code is never sent to anyone else, and OpFin staff will never ask you for it.</p>
    </AuthShell>
  );
}
