import ClubAccountingWorkspace from '@/components/club-accounting/Workspace';
import { notFound } from 'next/navigation';

export default async function ClubAccountingPage({params}:{params:Promise<{id:string}>}) {
  const {id}=await params;
  if(!/^[1-9][0-9]*$/.test(id)||!Number.isSafeInteger(Number(id)))notFound();
  return <ClubAccountingWorkspace key={id} spaceId={Number(id)}/>;
}
