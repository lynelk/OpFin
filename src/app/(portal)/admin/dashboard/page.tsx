import { PlaceholderPanel, Screen } from "@/components/Screen";

export default function AdminDashboardPage() {
  return (
    <Screen title="Admin dashboard" description="Operations overview placeholder for platform and operations roles.">
      <div className="grid grid-3">
        <section className="panel"><h2>Applications</h2><div className="stat">2</div></section>
        <section className="panel"><h2>Audit events</h2><div className="stat">3</div></section>
        <section className="panel"><h2>Open reviews</h2><div className="stat">1</div></section>
      </div>
      <PlaceholderPanel
        title="Admin APIs pending"
        text="This route is protected and role-aware, but operational API contracts still need backend endpoint definitions."
      />
    </Screen>
  );
}
