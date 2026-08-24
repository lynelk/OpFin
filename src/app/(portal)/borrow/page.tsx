import { JourneyCard } from "@/components/JourneyCard";
import { Screen } from "@/components/Screen";

export default function BorrowPage() {
  return (
    <Screen
      title="Borrow"
      description="Start with what you need, understand affordability before accepting an offer, and keep the whole loan journey in one place."
    >
      <div className="grid grid-3">
        <JourneyCard
          title="Check what fits"
          description="Review eligible products, amount, term and affordability before submitting an application."
          href="/loans/apply"
          action="Check options"
          status="pilot"
        />
        <JourneyCard
          title="Current loan"
          description="See the current loan position, confirmed payments and what needs attention next."
          href="/loans/account"
          action="View loan"
          status="pilot"
        />
        <JourneyCard
          title="Repayment plan"
          description="Review scheduled repayments. The production schedule contract is being consolidated behind the loan journey."
          href="/loans/schedule"
          action="View schedule"
          status="pilot"
        />
      </div>

      <section className="panel">
        <h2>Responsible borrowing rule</h2>
        <p className="muted">
          OpFin should show the amount received, total cost, fees, repayment dates and affordability impact before acceptance. A decline should explain the next practical path rather than become a dead end.
        </p>
      </section>
    </Screen>
  );
}
