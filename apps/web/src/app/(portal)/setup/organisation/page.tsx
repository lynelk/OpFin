import Link from "next/link";
import { getAccessToken } from "@/lib/auth/session";
import { createOrganisation, saveOrganisationStage } from "./actions";

const stages = ["profile", "kyb", "regulatory", "products", "integration", "certification"];
type Space = { id: number; name: string; type: string; role: string };
type Case = { stage?: string; status?: string; requirements?: string | Record<string, string> };
const apiBase = process.env.OPFIN_API_URL || process.env.NEXT_PUBLIC_OPFIN_API_URL;
async function authenticatedGet(path: string): Promise<Record<string, unknown> | null> {
  const token = await getAccessToken();
  if (!token || !apiBase || process.env.NEXT_PUBLIC_USE_MOCK_API === "true") return null;
  try {
    const res = await fetch(apiBase.replace(/\/$/, "") + path, { headers: { Accept: "application/json", Authorization: `Bearer ${token}` }, cache: "no-store" });
    if (!res.ok) return null;
    const json = await res.json();
    return json.data ?? null;
  } catch { return null; }
}
export default async function OrganisationSetup({ searchParams }: { searchParams: Promise<{ space?: string; stage?: string; error?: string; saved?: string }> }) {
  const p = await searchParams;
  const spacesResponse = await authenticatedGet("/financial-spaces");
  const spaces = (spacesResponse?.spaces ?? []) as Space[];
  const eligible = spaces.filter(s => ["business","sacco","investment_fund","partner","investment_club","savings_group"].includes(s.type) && ["owner","administrator","director","chairperson","treasurer"].includes(s.role));
  const id = Number(p.space);
  const selected = eligible.find(s => s.id === id);
  const workspace = selected ? await authenticatedGet(`/financial-spaces/${id}/workspace`) : null;
  const onboarding = workspace?.onboarding as Case | undefined;
  const requirements = typeof onboarding?.requirements === "string" ? (() => { try { return JSON.parse(onboarding.requirements) as Record<string,string>; } catch { return {}; } })() : (onboarding?.requirements ?? {});
  const current = stages.includes(p.stage ?? "") ? p.stage! : stages.includes(onboarding?.stage ?? "") ? onboarding!.stage! : "profile";
  const index = stages.indexOf(current);
  const next = stages[Math.min(index + 1, stages.length - 1)];
  return (
    <main className="panel">
      <p className="eyebrow">Organisations · Verified workspace</p>
      <h1>Set up your organisation</h1>
      <p>First register personally. Then create or resume a Financial Space. Organisation products remain restricted until regulatory and identity checks are complete.</p>
      {p.error && <div role="alert" className="placeholder state-server">Unable to {p.error === "create" ? "create the organisation" : p.error === "save" ? "save progress" : "validate your request"}. Check your account and try again.</div>}
      {p.saved && <p role="status">Progress saved. You can return to this workspace later.</p>}
      {!selected ? <>
        <h2>Resume existing organisation</h2>
        {eligible.length ? <ul>{eligible.map(s => <li key={s.id}><Link href={`/setup/organisation?space=${s.id}`}>{s.name} ({s.type.replaceAll("_"," ")})</Link></li>)}</ul> : <p>No existing organisation workspace is available for this account.</p>}
        <h2>Create an organisation Financial Space</h2>
        <form action={createOrganisation} className="form-grid">
          <label className="field">Organisation name<input name="name" required minLength={2} maxLength={160} /></label>
          <label className="field">Organisation type<select name="type" required>
            <option value="business">Business / employer</option><option value="sacco">SACCO</option><option value="investment_fund">Investment fund</option><option value="partner">Institutional partner</option><option value="investment_club">Investment club</option><option value="savings_group">Savings group</option>
          </select></label>
          <button className="button" type="submit">Create workspace</button>
        </form>
      </> : <>
        <h2>{selected.name}</h2>
        <p>Current status: {onboarding?.status ?? "not started"}. Select a stage to resume.</p>
        <nav aria-label="Onboarding stages">{stages.map(stage => <Link key={stage} href={`/setup/organisation?space=${id}&stage=${stage}`} style={{ marginRight: 12 }}>{stage}</Link>)}</nav>
        {["investment_club","savings_group"].includes(selected.type) ? <p>Group membership and governance configuration continues inside its Financial Space. Institutional KYB is not required for this group workflow.</p> : <>
          <form className="form-grid" action={saveOrganisationStage}>
            <input type="hidden" name="space" value={id} /><input type="hidden" name="stage" value={current}/><input type="hidden" name="next" value={next}/>
            <p>Stage {index+1} of {stages.length}: {current}</p>
            <label className="field">Registered organisation name<input name="organisation_name" defaultValue={requirements.organisation_name ?? selected.name} maxLength={160} required /></label>
            <label className="field">Registration number (if applicable)<input name="registration_number" defaultValue={requirements.registration_number ?? ""} maxLength={100}/></label>
            <label className="field">Contact email<input type="email" name="contact_email" defaultValue={requirements.contact_email ?? ""} maxLength={254}/></label>
            <label className="field">Website (optional)<input type="url" name="website" defaultValue={requirements.website ?? ""} maxLength={254}/></label>
            <button className="button" type="submit">Save and continue</button>
            {current === "certification" && <button className="button secondary" name="submit" value="yes" type="submit">Submit for review</button>}
          </form>
          <p className="muted">Submission is for review, not automatic approval or service activation.</p>
        </>}
        <Link href={`/spaces/${id}`}>Open authorised Financial Space</Link>
      </>}
    </main>
  );
}
