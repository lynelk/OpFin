import { JourneyCard } from "@/components/JourneyCard";
import { Screen } from "@/components/Screen";

export default function MorePage() {
  return (
    <Screen
      title="More"
      description="Manage verification, permissions, protection and help without crowding the main financial journeys."
    >
      <div className="grid grid-3">
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
          description="Insurance discovery, policies and claims will remain clearly separate from borrowing and will never be silently preselected."
          href="/insurance"
          action="Open protection"
          status="planned"
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
