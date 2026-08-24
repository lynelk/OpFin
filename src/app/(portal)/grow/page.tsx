import { JourneyCard } from "@/components/JourneyCard";
import { Screen } from "@/components/Screen";

export default function GrowPage() {
  return (
    <Screen
      title="Grow"
      description="Move from resilience to long-term growth through suitable, clearly explained investment products."
    >
      <div className="grid grid-2">
        <JourneyCard
          title="Investments"
          description="Review the existing investment workspace. Product suitability, risk labels and partner contracts will be activated progressively."
          href="/investments"
          action="Open investments"
          status="planned"
        />
        <JourneyCard
          title="Build before investing"
          description="OpFin will eventually combine emergency savings, debt position and suitability checks before recommending higher-risk products."
          href="/dashboard"
          action="Review Financial Compass"
          status="planned"
        />
      </div>
    </Screen>
  );
}
