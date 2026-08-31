import { Screen } from "@/components/Screen";

export default function WhatsAppPage() {
  return (
    <Screen
      title="OpFin on WhatsApp"
      description="Use selected OpFin journeys on WhatsApp without weakening account security, consent controls or auditability."
    >
      <div className="grid grid-2">
        <section className="panel">
          <p className="eyebrow">Secure session</p>
          <h2>Verify before personal information is shown</h2>
          <p className="muted">Request an OpFin OTP using your registered phone number, then send <strong>VERIFY 123456</strong> in WhatsApp. The verified session lasts 15 minutes and can be ended immediately with <strong>LOGOUT</strong>.</p>
          <div className="chip-row"><span className="badge ok">OTP-bound</span><span className="badge ok">15-minute expiry</span><span className="badge ok">Signed webhook</span><span className="badge ok">Replay protection</span></div>
        </section>
        <section className="panel">
          <p className="eyebrow">Audit trail</p>
          <h2>Every interaction is evidence</h2>
          <p className="muted">Inbound and outbound messages are timestamped, provider-message IDs are deduplicated and each message receives an integrity hash. Consent changes and support cases retain WhatsApp as the originating channel.</p>
          <div className="chip-row"><span className="badge ok">Message hashes</span><span className="badge ok">Channel attribution</span><span className="badge ok">Duplicate protection</span></div>
        </section>
      </div>

      <section className="panel">
        <h2>Journeys available in WhatsApp</h2>
        <div className="grid grid-3">
          <article className="case-card"><strong>STATUS</strong><p className="muted">Check KYC and open support-case status.</p></article>
          <article className="case-card"><strong>KYC</strong><p className="muted">Check identity-verification status without exposing documents.</p></article>
          <article className="case-card"><strong>CONSENTS</strong><p className="muted">Review active purpose-specific permissions.</p></article>
          <article className="case-card"><strong>GRANT CREDIT CONSENT</strong><p className="muted">Record explicit credit-processing permission with WhatsApp channel evidence.</p></article>
          <article className="case-card"><strong>REVOKE CREDIT CONSENT</strong><p className="muted">Revoke credit-processing permission immediately.</p></article>
          <article className="case-card"><strong>SUPPORT &lt;message&gt;</strong><p className="muted">Create a traceable support case and receive its reference.</p></article>
        </div>
      </section>

      <section className="panel">
        <p className="eyebrow">Step-up protection</p>
        <h2>Money and binding financial decisions stay behind stronger confirmation</h2>
        <p className="muted">WhatsApp can guide a customer to a repayment, transfer, withdrawal, investment or offer-acceptance journey, but it cannot silently complete those actions. OpFin requires step-up confirmation in the authenticated application before funds move or a regulated commitment becomes binding.</p>
        <div className="chip-row"><span className="badge warn">Payments</span><span className="badge warn">Repayments</span><span className="badge warn">Withdrawals</span><span className="badge warn">Investments</span><span className="badge warn">Offer acceptance</span></div>
      </section>
    </Screen>
  );
}
