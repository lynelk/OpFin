import { AuthShell } from "@/components/AuthShell";
import { StateNotice } from "@/components/Screen";

export default function AuthLoading() {
  return (
    <AuthShell
      eyebrow="Secure access"
      title="Opening OpFin"
      description="Checking the sign-in route and preparing your authorised workspace."
    >
      <StateNotice state="loading" message="Loading authentication..." />
    </AuthShell>
  );
}
