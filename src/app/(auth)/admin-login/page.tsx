import Link from "next/link";
import { loginAction } from "@/app/actions";

export default function AdminLoginPage() {
  return (
    <main className="auth-shell">
      <section className="auth-panel">
        <h1>Admin login</h1>
        <p>Sign in with backend credentials or use sandbox operations roles for local investor-demo walkthroughs.</p>
        <form action={loginAction} className="form-grid">
          <input type="hidden" name="next" value="/admin/dashboard" />
          <div className="field">
            <label htmlFor="admin-phone">Phone</label>
            <input id="admin-phone" name="phone" placeholder="256700000099" required />
          </div>
          <div className="field">
            <label htmlFor="admin-password">Password</label>
            <input id="admin-password" name="password" type="password" required />
          </div>
          <button className="button" type="submit">Sign in with backend</button>
        </form>
        <div className="auth-actions">
          <Link className="button secondary" href="/api/mock-login?role=platform_admin&next=/admin/dashboard">
            Sandbox platform admin
          </Link>
          <Link className="button secondary" href="/api/mock-login?role=operations&next=/admin/dashboard">
            Sandbox operations user
          </Link>
        </div>
      </section>
    </main>
  );
}
