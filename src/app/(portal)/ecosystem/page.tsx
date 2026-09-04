import { JourneyCard } from "@/components/JourneyCard";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";

export default async function EcosystemPage() {
  const token = await getAccessToken();
  try {
    const workspace = await longRangeApi.overview(token);

    return (
      <Screen title="Connected financial life" description="Choose the part of your financial life you want to manage. Each task has its own focused journey instead of one giant form.">
        <section className="panel compass-next-action">
          <p className="eyebrow">ONE IDENTITY, FOCUSED TASKS</p>
          <h2>Connect context only when it helps you.</h2>
          <p className="muted">OpFin reuses your verified identity and permissions. Provider-confirmed data remains clearly separate from information you enter yourself.</p>
        </section>

        <section className="panel">
          <div className="grid grid-2">
            <JourneyCard title="Connected accounts" description={`${workspace.linked_accounts.length} account(s) connected or awaiting verification.`} href="/connected-accounts" action="Manage accounts" status="available" />
            <JourneyCard title="Household" description={workspace.household ? "Household picture available." : "Add household context only when useful for planning."} href="/household-finance" action="Manage household" status="available" />
            <JourneyCard title="Microbusiness" description={workspace.microbusiness ? "Business picture available." : "Keep business and personal cash flow distinguishable."} href="/microbusiness" action="Manage business" status="available" />
            <JourneyCard title="Community finance" description={`${workspace.community_memberships.length} SACCO, VSLA, cooperative or group membership(s).`} href="/community-finance" action="Manage community" status="available" />
            <JourneyCard title="Asset finance" description={`${workspace.asset_finance.length} asset-finance request(s).`} href="/asset-finance" action="Open asset finance" status="available" />
            <JourneyCard title="Peer lending" description={`${workspace.participatory_listings.length} funding request(s) and ${workspace.participatory_commitments.length} lending commitment(s).`} href="/peer-lending" action="Open marketplace" status="available" />
            <JourneyCard title="Referrals" description={`${workspace.referrals.length} referral event(s).`} href="/referrals" action="Manage referrals" status="available" />
          </div>
        </section>

        <section className="panel">
          <h2>Why this is simpler</h2>
          <p className="muted">You never need to complete every section. OpFin deep-links to the exact missing information only when a financial journey genuinely needs it.</p>
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Connected financial life" description="Manage connected financial context in focused journeys."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load connected financial life."} /></Screen>;
  }
}
