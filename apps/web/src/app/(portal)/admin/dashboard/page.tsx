import Link from "next/link";
import { Screen } from "@/components/Screen";

export default function AdminDashboardPage() {
  return (
    <Screen title="Admin dashboard" description="Operational controls for governed credit, payments, customer support and platform configuration.">
      <div className="grid grid-3">
        <section className="panel"><h2>Credit review</h2><p className="muted">Review applications, decisions and offers through the production credit workflow.</p><Link className="button secondary" href="/admin/credit-review">Open credit review</Link></section>
        <section className="panel"><h2>Reconciliation</h2><p className="muted">Investigate OpFin and CPay evidence mismatches and resolve reconciliation items.</p><Link className="button secondary" href="/admin/reconciliation">Open reconciliation</Link></section>
        <section className="panel"><h2>Platform governance</h2><p className="muted">Manage product definitions, decision rules, workflows and hardship approvals with maker-checker controls.</p><Link className="button secondary" href="/admin/platform-governance">Open governance</Link></section>
      </div>
      <div className="grid grid-3">
        <section className="panel"><h2>Ledger</h2><Link className="button secondary" href="/admin/ledger">Review ledger</Link></section>
        <section className="panel"><h2>Compliance</h2><Link className="button secondary" href="/admin/compliance">Open compliance</Link></section>
        <section className="panel"><h2>Support</h2><Link className="button secondary" href="/admin/support">Open support</Link></section>
      </div>
    </Screen>
  );
}
