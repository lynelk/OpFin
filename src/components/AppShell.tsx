import Link from "next/link";
import type { ReactNode } from "react";
import { canSeeGroup, getCurrentSession } from "@/lib/auth/session";
import { navigationItems } from "@/lib/navigation";

export async function AppShell({ children }: Readonly<{ children: ReactNode }>) {
  const session = await getCurrentSession();
  const visibleItems = navigationItems.filter((item) => {
    if (!canSeeGroup(session.role, item.group)) return false;
    return !item.roles || item.roles.includes(session.role);
  });

  const groups = [
    ["customer", "Customer"],
    ["wealth", "Future modules"],
    ["admin", "Operations"],
    ["employer", "Employer"]
  ] as const;

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Link className="brand" href="/dashboard">
          <span className="brand-mark">OF</span>
          <span>OpFin</span>
        </Link>
        {groups.map(([group, title]) => {
          const groupItems = visibleItems.filter((item) => item.group === group);
          if (groupItems.length === 0) return null;

          return (
            <nav className="nav-group" key={group} aria-label={title}>
              <p className="nav-title">{title}</p>
              {groupItems.map((item) => (
                <Link className="nav-link" href={item.href} key={item.href}>
                  {item.label}
                </Link>
              ))}
            </nav>
          );
        })}
      </aside>
      <main className="main">
        <header className="topbar">
          <div>
            <strong>{session.name}</strong>
            <p className="muted">Role: {session.role}</p>
          </div>
          <Link className="button secondary" href="/login">
            Switch role
          </Link>
        </header>
        <div className="content">{children}</div>
      </main>
    </div>
  );
}
