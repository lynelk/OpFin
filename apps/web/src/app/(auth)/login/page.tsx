import Link from "next/link";
import { loginAction } from "@/app/actions";
import { AuthShell } from "@/components/AuthShell";

const contextEyebrow: Record<string, string> = {
  personal: "Personal & Financial Spaces",
  employer: "Employer Workspace",
  partner: "Programme Partner Workspace",
  operations: "OpFin Operations"
};

export default async function LoginPage({
  searchParams
}: {
  searchParams?: Promise<{ error?: string; message?: string; next?: string; context?: string }>;
}) {
  const params = await searchParams;
  const requestedNext = params?.next ?? "";
  const deletingAccount = requestedNext === "/account/delete";
  const context = params?.context ?? "";
  const demoShortcutsEnabled =
    process.env.OPFIN_ENABLE_DEMO_SHORTCUTS === "true" && process.env.NODE_ENV !== "production";

  const eyebrow = deletingAccount
    ? "Account control"
    : contextEyebrow[context] ?? "Secure access";

  const description = deletingAccount
    ? "Use your OpFin phone number and PIN to verify that this deletion request belongs to you. Legacy Web accounts may use their existing password."
    : "Use your OpFin phone number and PIN. Legacy Web accounts may use their existing password. OpFin then opens the workspace your account is authorised to use.";

  const sandboxNext =
    requestedNext ||
    (context === "operations"
      ? "/admin/dashboard"
      : context === "employer"
        ? "/employer"
        : context === "partner"
          ? "/partner/impact"
          : "/dashboard");

  return (
    <AuthShell
      eyebrow={eyebrow}
      title={deletingAccount ? "Verify your OpFin account" : "Sign in to OpFin"}
      description={description}
      footer={
        <div className="auth-help-row">
          <Link href="/">Back to OpFin</Link>
          {!deletingAccount ? <Link href="/programme-partner/activate">Activate programme invitation</Link> : null}
        </div>
      }
    >
      {params?.message ? (
        <div className={"placeholder state-" + (params.error ?? "server") + " auth-notice"} role="alert">
          <strong>Sign-in problem</strong>
          <p>{params.message}</p>
        </div>
      ) : null}

      <form action={loginAction} className="form-grid">
        <input type="hidden" name="next" value={requestedNext} />
        <input type="hidden" name="context" value={context} />
        <div className="field">
          <label htmlFor="phone">Phone number</label>
          <input
            id="phone"
            name="phone"
            inputMode="tel"
            autoComplete="tel"
            placeholder="+256 700 000 001"
            required
          />
        </div>
        <div className="field">
          <label htmlFor="password">PIN or password</label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
          />
        </div>
        <button className="button auth-primary-action" type="submit">
          {deletingAccount ? "Verify and continue" : "Sign in"}
        </button>
      </form>

      <p className="auth-note">
        New customers create their account in the OpFin App. Web access is for existing customers and authorised Workspace users.
      </p>

      {demoShortcutsEnabled ? (
        <div className="auth-actions">
          {context === "operations" ? (
            <>
              <Link className="button secondary" href={"/api/mock-login?role=platform_admin&next=" + encodeURIComponent(sandboxNext)}>
                Sandbox platform admin
              </Link>
              <Link className="button secondary" href={"/api/mock-login?role=operations&next=" + encodeURIComponent(sandboxNext)}>
                Sandbox operations user
              </Link>
            </>
          ) : context === "employer" ? (
            <Link className="button secondary" href={"/api/mock-login?role=employer_admin&next=" + encodeURIComponent(sandboxNext)}>
              Sandbox employer admin
            </Link>
          ) : context === "partner" ? (
            <Link className="button secondary" href={"/api/mock-login?role=programme_partner&next=" + encodeURIComponent(sandboxNext)}>
              Sandbox programme partner
            </Link>
          ) : (
            <Link className="button secondary" href={"/api/mock-login?role=customer&next=" + encodeURIComponent(sandboxNext)}>
              Sandbox customer
            </Link>
          )}
        </div>
      ) : null}
    </AuthShell>
  );
}
