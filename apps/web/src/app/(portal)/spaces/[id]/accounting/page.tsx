import Link from 'next/link';
import {notFound} from 'next/navigation';
import ClubAccountingWorkspace from '@/components/club-accounting/Workspace';
import ClubRecovery from '@/components/club-accounting/Recovery';

export default async function ClubAccountingPage({params}:{params:Promise<{id:string}>}) {
  const {id}=await params;
  if(!/^[1-9][0-9]*$/.test(id)||!Number.isSafeInteger(Number(id)))notFound();
  return <><Link className="button secondary" href="/club-history">My retained club history</Link><ClubRecovery spaceId={Number(id)}/><ClubAccountingWorkspace key={id} spaceId={Number(id)}/></>;
}
