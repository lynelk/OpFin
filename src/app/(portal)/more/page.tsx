import { JourneyCard } from "@/components/JourneyCard";
import { Screen } from "@/components/Screen";

export default function MorePage() {
  return (
    <Screen title="More" description="Occasional tasks and account controls live here so Home, Borrow, Save and Grow stay focused.">
      <section className="panel">
        <h2>Account & trust</h2>
        <div className="grid grid-2">
          <JourneyCard title="Your OpFin setup" description="Finish only the essential setup steps and reuse verified information across journeys." href="/setup" action="Review setup" status="available" />
          <JourneyCard title="Identity & KYC" description="See your verification status and continue only the step that is incomplete." href="/kyc" action="Manage verification" status="available" />
          <JourneyCard title="Data permissions" description="Review purpose-specific permissions and revoke them where permitted." href="/consent" action="Manage permissions" status="available" />
          <JourneyCard title="Security Centre" description="Freeze transactions, manage alerts and review account-security activity." href="/security" action="Manage security" status="available" />
        </div>
      </section>

      <section className="panel">
        <h2>Connected financial life</h2>
        <div className="grid grid-2">
          <JourneyCard title="Connected accounts & context" description="Manage accounts, household, microbusiness and community relationships through separate focused tasks." href="/ecosystem" action="Open connected life" status="available" />
          <JourneyCard title="Asset finance" description="Request asset financing and complete only independently approved deposits." href="/asset-finance" action="Open asset finance" status="available" />
          <JourneyCard title="Peer lending" description="Borrow from investors or lend to independently reviewed borrowers through the OpFin Marketplace." href="/peer-lending" action="Open marketplace" status="available" />
          <JourneyCard title="OpFin on WhatsApp" description="Use verified WhatsApp for status, KYC, consent and support. Binding financial actions still require step-up." href="/whatsapp" action="Review WhatsApp access" status="available" />
        </div>
      </section>

      <section className="panel">
        <h2>Plan & automate</h2>
        <div className="grid grid-3">
          <JourneyCard title="Money plan" description="Review cash flow, budgets and safe-to-spend from one place." href="/money" action="Open money plan" status="available" />
          <JourneyCard title="Financial calendar" description="See confirmed, scheduled and estimated future cash events." href="/calendar" action="Open calendar" status="available" />
          <JourneyCard title="Money Autopilot" description="Manage capped, user-authorised routines without bypassing provider settlement controls." href="/money-autopilot" action="Manage automation" status="available" />
        </div>
      </section>

      <section className="panel">
        <h2>Resilience & help</h2>
        <div className="grid grid-2">
          <JourneyCard title="Financial Passport" description="Review your provenance-labelled financial record." href="/financial-passport" action="Open passport" status="available" />
          <JourneyCard title="Credit Builder" description="Use confirmed repayment behaviour to create an improvement plan." href="/credit-builder" action="Build credit plan" status="available" />
          <JourneyCard title="Financial hardship" description="Report a material financial shock and request independent relief review." href="/hardship" action="Request assistance" status="available" />
          <JourneyCard title="Payment status" description="Check whether OpFin records agree with governed CPay evidence." href="/payment-status" action="Review payments" status="available" />
          <JourneyCard title="Protection" description="Manage insurance separately from borrowing so optional cover is never silently bundled." href="/insurance" action="Open protection" status="available" />
          <JourneyCard title="Support" description="Create and track a case without repeating details OpFin already knows." href="/support" action="Get help" status="available" />
        </div>
      </section>
    </Screen>
  );
}
