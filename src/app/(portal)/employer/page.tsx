import { PlaceholderPanel, Screen } from "@/components/Screen";

export default function EmployerPortalPage() {
  return (
    <Screen title="Employer portal" description="Employer-linked benefits placeholder for future backend contracts.">
      <PlaceholderPanel
        title="Employer module pending"
        text="The backend does not yet expose employer benefit endpoints. This route is protected for employer admin and platform admin roles."
      />
    </Screen>
  );
}
