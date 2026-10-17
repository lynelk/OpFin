"use client";

import { useEffect, useState, type ReactNode } from "react";
import { usePathname } from "next/navigation";

/** Collapses long role-specific menus on mobile without changing permissions or routes. */
export function MobileSidebar({ children }: Readonly<{ children: ReactNode }>) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  useEffect(() => setOpen(false), [pathname]);

  return (
    <>
      <button
        type="button"
        className="sidebar-menu-toggle"
        aria-expanded={open}
        aria-controls="opfin-sidebar-navigation"
        onClick={() => setOpen((current) => !current)}
      >
        <span>Navigation</span>
        <span aria-hidden="true">{open ? "−" : "+"}</span>
      </button>
      <div id="opfin-sidebar-navigation" className={`sidebar-navigation${open ? " is-open" : ""}`}>
        {children}
      </div>
    </>
  );
}
