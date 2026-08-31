import { JourneyCard } from "@/components/JourneyCard";
import { Screen } from "@/components/Screen";

export default function MorePage() {
  return (
    <Screen
      title="More"
      description="Everything important that supports your main financial journeys, grouped by purpose so you do not have to memorise the platform."
    >
      <section className="panel">
        <h2>Account & trust</h2>
        <p className="muted">Setup, identity, permissions and security belong together because they determine what OpFin can safely do for you.</p>
        <div className="grid grid-2">
          <JourneyCard title="Your OpFin setup" description="Review activation progress, choose your current financial priority and control useful progress reminders." href="/setup" action="Review setup" status="available" />
          <JourneyCard title="Identity & KYC" description="Review your verification status and continue exactly the identity step that is still incomplete." href="/kyc" action="Manage verification" status="pilot" />
          <JourneyCard title="Data permissions" description="Review purpose-specific consents and revoke them where the product and law permit." href="/consent" action="Manage permissions" status="available" />
          <JourneyCard title="Security Centre" description="Freeze transactions, manage login and payment alerts, and review recent account-security activity." href="/security" action="Manage security" status="available" />
        </div>
      </section>

      <section className="panel">
        <h2>Plan & automate</h2>
        <p className="muted">Understand your cash flow first, then automate only the routines you have explicitly authorised.</p>
        <div className="grid grid-3">
          <JourneyCard title="Money plan & budgets" description="Record current balances, review safe-to-spend, set category budgets and correct cash-flow categories." href="/money" action="Open money plan" status="available" />
          <JourneyCard title="Financial calendar" description="See confirmed, scheduled and estimated future cash events, including OpFin loan obligations." href="/calendar" action="Open calendar" status="available" />
          <JourneyCard title="Money Autopilot" description="Create capped, user-authorised rules for routine financial progress while provider settlement remains governed separately." href="/money-autopilot" action="Manage automation" status="pilot" />
        </div>
      </section>

      <section className="panel">
        <h2>Resilience & financial record</h2>
        <p className="muted">Build a stronger record, understand payment evidence and get structured help before a financial shock becomes a default.</p>
        <div className="grid grid-2">
          <JourneyCard title="Financial Passport" description="Review a provenance-labelled snapshot of identity, consent, balances and confirmed OpFin debt." href="/financial-passport" action="Open passport" status="available" />
          <JourneyCard title="Credit Builder" description="Create an improvement plan using confirmed repayment behaviour without fabricating a bureau score." href="/credit-builder" action="Build credit plan" status="available" />
          <JourneyCard title="Financial Shock & Hardship" description="Report a material financial shock and request relief for independent review before taking more debt where possible." href="/hardship" action="Request assistance" status="available" />
          <JourneyCard title="Payment status" description="See whether OpFin transaction records agree with governed CPay payment evidence." href="/payment-status" action="Review payments" status="available" />
        </div>
      </section>

      <section className="panel">
        <h2>Protection & help</h2>
        <p className="muted">Protection, assisted channels and support remain available without competing with your everyday Home, Borrow, Save and Grow journeys.</p>
        <div className="grid grid-3">
          <JourneyCard title="Protection" description="Review insurance products, policies and claims separately from borrowing. Optional cover is never silently preselected." href="/insurance" action="Open protection" status="pilot" />
          <JourneyCard title="OpFin on WhatsApp" description="Use secure, OTP-bound WhatsApp journeys for status, KYC, consent and support, with step-up protection for money actions." href="/whatsapp" action="Review WhatsApp access" status="pilot" />
          <JourneyCard title="Support" description="Create a case, keep its reference and follow its status without repeating transaction details OpFin already knows." href="/support" action="Get help" status="pilot" />
        </div>
      </section>
    </Screen>
  );
}
