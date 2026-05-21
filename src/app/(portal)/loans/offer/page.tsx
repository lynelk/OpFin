import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";

export default async function LoanOfferPage() {
  const offer = await opfinApi.loanOffer();

  return (
    <Screen
      title="Loan offer"
      description="Offer route prepared for the future offer module without assuming backend offer fields."
      action={<Link className="button" href="/loans/schedule">Review schedule</Link>}
    >
      <section className="panel">
        <h2>{offer.data.status}</h2>
        <StateNotice state="sandbox" message={offer.data.message} />
      </section>
    </Screen>
  );
}
