import Link from 'next/link';
import SpacesContent from './spaces-content';
export default function SpacesPage() {
  return <><nav aria-label="Retained member records"><Link className="button secondary" href="/club-history">My club capital history, including former memberships</Link></nav><SpacesContent/></>;
}
