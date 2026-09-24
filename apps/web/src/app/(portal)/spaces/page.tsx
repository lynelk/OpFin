import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { financialSpacesApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

const labels:Record<string,string>={personal:"My money",household:"Household",savings_group:"Savings group",investment_club:"Investment club",business:"Business",sacco:"SACCO",investment_fund:"Investment / fund",partner:"Financial partner"};

export default async function SpacesPage(){
 const token=await getAccessToken();
 try{
  const response=await financialSpacesApi.list(token); const spaces=response.data.spaces;
  return <Screen title="My financial spaces" description="One identity can belong to many financial contexts. Personal money stays private; group and organisation access follows your role.">
   <section className="panel"><div className="case-card-head"><div><h2>Understand all the money you manage</h2><p className="muted">Individuals and savings groups remain fully usable from the App. Web gives you more room for analysis, reporting and institutional work.</p></div></div></section>
   <div className="grid grid-2">{spaces.map(space=><article className="panel" key={space.id}><p className="eyebrow">{labels[space.type]??space.type}</p><h2>{space.name}</h2><p className="muted">Role: {space.role} · {space.currency}</p><Link className="button" href={`/spaces/${space.id}`}>Open space</Link></article>)}</div>
  </Screen>;
 }catch(error){return <Screen title="My financial spaces" description="Your authorised financial contexts appear here."><StateNotice state="server" message={error instanceof Error?error.message:"Unable to load spaces."}/></Screen>}
}
