import Link from "next/link";

export default function AdminLoginPage() {
  return (
    <main className="auth-shell">
      <section className="auth-panel">
        <h1>Admin login</h1>
        <p>This placeholder uses mock roles until backend admin authentication contracts are finalized.</p>
        <div className="auth-actions">
          <Link className="button" href="/api/mock-login?role=platform_admin&next=/admin/dashboard">
            Continue as platform admin
          </Link>
          <Link className="button secondary" href="/api/mock-login?role=operations&next=/admin/dashboard">
            Continue as operations user
          </Link>
        </div>
      </section>
    </main>
  );
}
