"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";

/** Only visualises the authorised destinations already filtered by the server AppShell. */
export function ActiveNavLink({
  href,
  children
}: Readonly<{ href: string; children: ReactNode }>) {
  const pathname = usePathname();
  const active = pathname === href || (href !== "/dashboard" && pathname.startsWith(`${href}/`));

  return (
    <Link className={`nav-link${active ? " is-active" : ""}`} href={href} aria-current={active ? "page" : undefined}>
      {children}
    </Link>
  );
}
