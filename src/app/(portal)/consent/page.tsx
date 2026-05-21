import { PlaceholderPanel, Screen } from "@/components/Screen";

export default function ConsentPage() {
  return (
    <Screen title="Consent management" description="Consent UI shell for CRB, mobile money, and employer-linked permissions.">
      <PlaceholderPanel
        title="Consent contract pending"
        text="The backend audit found no dedicated consent API yet. This screen is wired as a route and UI placeholder without inventing consent fields."
      />
    </Screen>
  );
}
