import Link from "next/link";
import SignupClient from "./SignupClient";

export default function SignupPage() {
  if (process.env.OPFIN_WEB_SIGNUP_ENABLED !== "true") {
    return (
      <main className="auth-shell">
        <section className="panel" role="status">
          <h1>Web registration is being prepared</h1>
          <p>New-customer registration is currently available through the OpFin App. Existing customers can use the secure Web sign-in.</p>
          <Link href="/login">Sign in to OpFin</Link>
        </section>
      </main>
    );
  }
  return <SignupClient />;
}
