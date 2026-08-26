import { JourneyCard } from "@/components/JourneyCard";
import { Screen } from "@/components/Screen";

export default function MorePage() {
  return (
    <Screen
      title="More"
      description="Manage your money plan, calendar, verification, permissions, protection and help without crowding the five main journeys."
    >
      <div className="grid grid-3">
        <JourneyCard
          title="Money plan & budgets"
          description="Record current balances, review safe-to-spend, set category budgets and correct cash-flow categories."
          href="/money"
          action="Open money plan"
          status="available"
        />
        <JourneyCard
          title="Financial calendar"
          description="See confirmed, scheduled and estimated future cash events, including OpFin loan obligations."
          href="/calendar"
          action="Open calendar"
          status="available"
        />
        <JourneyCard
          title="Identity & KYC"
          description="Review your verification status and continue an incomplete identity check."
          href="/kyc"
          action="Manage verification"
          status="pilot"
        />
        <JourneyCard
          title="Data permissions"
          description="Review purpose-specific consents and revoke them where the product and law permit."
          href="/consent"
          action="Manage permissions"
          status="available"
        />
        <JourneyCard
          title="Protection"
          description="Review insurance products, policies and claims separately from borrowing. Optional cover is never silently preselected."
          href="/insurance"
          action="Open protection"
          status="pilot"
        />
        <JourneyCard
          title="Support"
          description="Create a case, keep its reference and follow its status without repeating transaction details OpFin already knows."
          href="/support"
          action="Get help"
          status="pilot"
        />
      </div>
    </Screen>
  );
}
