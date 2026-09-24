import { OpFinSymbol } from "./OpFinSymbol";
import Link from "next/link";
import type { ReactNode } from "react";
import { logoutAction } from "@/app/actions";
import { canSeeGroup, getCurrentSession } from "@/lib/auth/session";
import { navigationItems } from "@/lib/navigation";
import { homeForRole } from "@/lib/access";

export async function AppShell({ children }: Readonly<{ children: ReactNode }>) {
  const session = await getCurrentSession();
  const homeHref = homeForRole(session.role);
  const visibleItems = navigationItems.filter((item) => {
    if (!canSeeGroup(session.role, item.group)) return false;
    return !item.roles || item.roles.includes(session.role);
  });

  const groups = [
    ["customer", "Your money"],
    ["admin", "Operations"],
    ["employer", "Institutional"],
    ["partner", "Programme"]
  ] as const;

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Link className="brand" href={homeHref} aria-label="OpFin home">
          <span className="brand-mark"><OpFinSymbol reverse /></span>
          <span>OpFin</span>
        </Link>
        {groups.map(([group, title]) => {
          const groupItems = visibleItems.filter((item) => item.group === group);
          if (groupItems.length === 0) return null;

          const sections = Array.from(new Set(groupItems.map((item) => item.section ?? "")));
          const sectioned = sections.some(Boolean);

          return (
            <nav className="nav-group" key={group} aria-label={title}>
              {!sectioned ? <p className="nav-title">{title}</p> : null}
              {sections.map((section) => (
                <div key={section || "default"}>
                  {section ? <p className="nav-title">{section}</p> : null}
                  {groupItems
                    .filter((item) => (item.section ?? "") === section)
                    .map((item) => (
                      <Link className="nav-link" href={item.href} key={item.href}>
                        {item.label}
                      </Link>
                    ))}
                </div>
              ))}
            </nav>
          );
        })}
      </aside>
      <main className="main">
        <header className="topbar">
          <div>
            <strong>{session.name}</strong>
            <p className="muted">{session.role === "customer" ? "Your OpFin account" : `Role: ${session.role}`}</p>
          </div>
          <form action={logoutAction}>
            <button className="button secondary" type="submit">Sign out</button>
          </form>
        </header>
        <div className="content">{children}</div>
      </main>
    </div>
  );
}
