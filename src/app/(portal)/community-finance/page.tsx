import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { getAccessToken } from "@/lib/auth/session";
import { longRangeApi } from "@/lib/api/long-range";
import { OpfinApiError } from "@/lib/api/errors";
import { joinCommunityFocusedAction } from "@/app/focused-long-range-actions";

function text(value: unknown): string { return typeof value === "string" ? value : String(value ?? ""); }

export default async function CommunityFinancePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};
  try {
    const workspace = await longRangeApi.overview(token);
    return (
      <Screen title="Community finance" description="Connect a SACCO, VSLA, cooperative, association or employer group only when it can improve verification or access to relevant financial services.">
        {params.status ? <StateNotice state="success" message="Membership submitted for verification." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to add that membership."} /> : null}
        <section className="panel">
          <form action={joinCommunityFocusedAction} className="form-grid">
            <div className="field"><label htmlFor="institution_type">Community type</label><select id="institution_type" name="institution_type" defaultValue="sacco"><option value="sacco">SACCO</option><option value="vsla">VSLA</option><option value="cooperative">Cooperative</option><option value="association">Association</option><option value="employer_group">Employer group</option></select></div>
            <div className="field"><label htmlFor="institution_name">Name</label><input id="institution_name" name="institution_name" required /></div>
            <div className="field"><label htmlFor="member_reference">Member reference, if available</label><input id="member_reference" name="member_reference" /></div>
            <button className="button" type="submit">Add for verification</button>
          </form>
        </section>
        <section className="panel">
          <div className="case-card-head"><h2>Your memberships</h2><Link href="/ecosystem">Back to connected life</Link></div>
          {workspace.community_memberships.length === 0 ? <p className="muted">No community memberships connected.</p> : <div className="case-list">{workspace.community_memberships.map((membership) => <article className="case-card" key={membership.id}><div className="case-card-head"><strong>{text(membership.institution_name)}</strong><span className="badge">{text(membership.status).replaceAll("_", " ")}</span></div><p className="muted">{text(membership.institution_type).toUpperCase()} · {text(membership.member_reference) || "Reference not supplied"}</p></article>)}</div>}
        </section>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="Community finance" description="Manage verified community relationships."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load community finance."} /></Screen>;
  }
}
