import { JourneyCard } from "@/components/JourneyCard";
import { Screen } from "@/components/Screen";

export default function MorePage() {
  return (
    <Screen
      title="More"
      description="Manage verification, permissions, protection, account security and financial resilience without crowding the five main journeys."
    >
      <div className="grid grid-3">
        <JourneyCard title="Money plan & budgets" description="Record current balances, review safe-to-spend, set category budgets and correct cash-flow categories." href="/money" action="Open money plan" status="available" />
        <JourneyCard title="Financial calendar" description="See confirmed, scheduled and estimated future cash events, including OpFin loan obligations." href="/calendar" action="Open calendar" status="available" />
        <JourneyCard title="Security Centre" description="Freeze transactions, manage login and payment alerts, and review recent account-security activity." href="/security" action="Manage security" status="available" />
        <JourneyCard title="Financial Passport" description="Review a provenance-labelled snapshot of identity, consent, balances and confirmed OpFin debt." href="/financial-passport" action="Open passport" status="available" />
        <JourneyCard title="Credit Builder" description="Create an improvement plan using confirmed repayment behaviour without fabricating a bureau score." href="/credit-builder" action="Build credit plan" status="available" />
        <JourneyCard title="Financial Shock & Hardship" description="Report a material financial shock and request relief for independent review." href="/hardship" action="Request assistance" status="available" />
        <JourneyCard title="Payment status" description="See whether OpFin transaction records agree with governed CPay payment evidence." href="/payment-status" action="Review payments" status="available" />
        <JourneyCard title="Identity & KYC" description="Review your verification status and continue an incomplete identity check." href="/kyc" action="Manage verification" status="pilot" />
        <JourneyCard title="Data permissions" description="Review purpose-specific consents and revoke them where the product and law permit." href="/consent" action="Manage permissions" status="available" />
        <JourneyCard title="Protection" description="Review insurance products, policies and claims separately from borrowing. Optional cover is never silently preselected." href="/insurance" action="Open protection" status="pilot" />
        <JourneyCard title="Support" description="Create a case, keep its reference and follow its status without repeating transaction details OpFin already knows." href="/support" action="Get help" status="pilot" />
      </div>
    </Screen>
  );
}
