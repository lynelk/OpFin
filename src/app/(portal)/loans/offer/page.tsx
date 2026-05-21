import Link from "next/link";
import { PlaceholderPanel, Screen } from "@/components/Screen";

export default function LoanOfferPage() {
  return (
    <Screen
      title="Loan offer"
      description="Offer route prepared for the future offer module without assuming backend offer fields."
      action={<Link className="button" href="/loans/schedule">Review schedule</Link>}
    >
      <PlaceholderPanel
        title="Offer API pending"
        text="The backend checkpoint identified loan offers as missing. This page is intentionally contract-safe."
      />
    </Screen>
  );
}
