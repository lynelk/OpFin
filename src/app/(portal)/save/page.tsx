import { JourneyCard } from "@/components/JourneyCard";
import { Screen } from "@/components/Screen";

export default function SavePage() {
  return (
    <Screen
      title="Save"
      description="Build resilience around goals first. Savings pockets, emergency funds, scheduled contributions and Money Autopilot will live here as they become available."
    >
      <div className="grid grid-3">
        <JourneyCard
          title="Savings"
          description="Use the existing savings workspace while the goal-based product model is rebuilt behind it."
          href="/savings"
          action="Open savings"
          status="planned"
        />
        <JourneyCard
          title="Emergency fund"
          description="A dedicated resilience goal will help separate emergency money from everyday spending."
          href="/savings"
          action="Review savings"
          status="planned"
        />
        <JourneyCard
          title="Money Autopilot"
          description="Future rules will allocate salary or remittance income into savings and other approved goals through CPay."
          href="/more"
          action="See roadmap status"
          status="planned"
        />
      </div>
    </Screen>
  );
}
