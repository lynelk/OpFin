import Link from 'next/link';
import TreasuryContent from './treasury-content';

export default async function TreasuryPage(props: Parameters<typeof TreasuryContent>[0]) {
  const {id}=await props.params;
  return <>
    <nav aria-label="Club finance workspaces" className="inline-form">
      <Link className="button secondary" href={'/spaces/'+id+'/accounting'}>Member capital, investments and accounting</Link>
      <Link className="button secondary" href={'/spaces/'+id+'/treasury'}>Cashbook and external statement reconciliation</Link>
    </nav>
    <TreasuryContent {...props}/>
  </>;
}
