import { Screen, StateNotice } from "@/components/Screen";
import { financialSpacesApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function SpacePage({params}:{params:Promise<{id:string}>}){
 const {id}=await params; const token=await getAccessToken(); const spaceId=Number(id);
 try{const [spaces,life,workspace]=await Promise.all([financialSpacesApi.list(token),financialSpacesApi.financialLife(spaceId,token),financialSpacesApi.workspace(spaceId,token).catch(()=>null)]);
 const space=spaces.data.spaces.find(x=>x.id===spaceId); if(!space)throw new Error("Space not found.");
 return <Screen title={space.name} description="Your authorised view of this Financial Space.">
  <div className="grid grid-3"><section className="panel"><h2>Net worth / position</h2><div className="stat">{formatUgx(life.data.net_worth_minor)}</div></section><section className="panel"><h2>Safe to spend</h2><div className="stat">{formatUgx(life.data.safe_to_spend_minor)}</div></section><section className="panel"><h2>Debt</h2><div className="stat">{formatUgx(life.data.debt_minor)}</div></section></div>
  <div className="grid grid-3"><section className="panel"><h2>Assets</h2><div className="stat">{formatUgx(life.data.assets_minor)}</div></section><section className="panel"><h2>Owed to this space</h2><div className="stat">{formatUgx(life.data.receivables_minor)}</div></section><section className="panel"><h2>Upcoming 30 days</h2><div className="stat">{formatUgx(life.data.upcoming_30d_minor)}</div></section></div>
  {workspace?<section className="panel"><h2>Workspace</h2><p className="muted">Institutional controls, capabilities and onboarding status are available according to your role. The organisation does not inherit access to your Personal Space.</p></section>:null}
 </Screen>}catch(error){return <Screen title="Financial space" description="Your authorised financial context."><StateNotice state="server" message={error instanceof Error?error.message:"Unable to load this space."}/></Screen>}
}
