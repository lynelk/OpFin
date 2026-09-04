import Link from "next/link";
import { loginAction } from "@/app/actions";

export default async function LoginPage({ searchParams }: { searchParams?: Promise<{ error?: string; message?: string; next?: string }> }) {
  const params = await searchParams;
  const next = params?.next ?? "/dashboard";
  const demoShortcutsEnabled = process.env.OPFIN_ENABLE_DEMO_SHORTCUTS === "true" && process.env.NODE_ENV !== "production";

  return (
    <main className="auth-shell">
      <section className="auth-panel">
        <h1>Sign in to OpFin</h1>
        <p>Use backend phone authentication to access OpFin.</p>
        {params?.message ? (
          <div className={`placeholder state-${params.error ?? "server"}`}>
            <strong>{params.error ?? "Login error"}</strong>
            <p>{params.message}</p>
          </div>
        ) : null}
        <form action={loginAction} className="form-grid">
          <input type="hidden" name="next" value={next} />
          <div className="field">
            <label htmlFor="phone">Phone</label>
            <input id="phone" name="phone" placeholder="256700000001" required />
          </div>
          <div className="field">
            <label htmlFor="password">Password</label>
            <input id="password" name="password" type="password" required />
          </div>
          <button className="button" type="submit">Sign in with backend</button>
        </form>
        <div className="auth-actions">
          {demoShortcutsEnabled ? (
            <Link className="button secondary" href={`/api/mock-login?role=customer&next=${encodeURIComponent(next)}`}>
              Sandbox customer
            </Link>
          ) : null}
          <Link className="button secondary" href="/admin-login">
            Admin login
          </Link>
        </div>
      </section>
    </main>
  );
}
