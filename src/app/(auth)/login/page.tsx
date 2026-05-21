import Link from "next/link";

export default async function LoginPage({ searchParams }: { searchParams?: Promise<{ next?: string }> }) {
  const params = await searchParams;
  const next = params?.next ?? "/dashboard";

  return (
    <main className="auth-shell">
      <section className="auth-panel">
        <h1>Sign in to OpFin</h1>
        <p>Use the mock role selector while backend session contracts are completed.</p>
        <div className="auth-actions">
          <Link className="button" href={`/api/mock-login?role=customer&next=${encodeURIComponent(next)}`}>
            Continue as customer
          </Link>
          <Link className="button secondary" href="/admin-login">
            Admin login
          </Link>
        </div>
      </section>
    </main>
  );
}
