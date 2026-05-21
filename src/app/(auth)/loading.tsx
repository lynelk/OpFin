import { StateNotice } from "@/components/Screen";

export default function AuthLoading() {
  return (
    <main className="auth-shell">
      <section className="auth-panel">
        <StateNotice state="loading" message="Loading authentication..." />
      </section>
    </main>
  );
}
