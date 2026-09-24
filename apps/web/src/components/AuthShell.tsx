import Link from "next/link";
import type { ReactNode } from "react";
import { OpFinSymbol } from "./OpFinSymbol";

export function AuthShell({
  eyebrow,
  title,
  description,
  children,
  footer
}: Readonly<{
  eyebrow?: string;
  title: string;
  description: string;
  children: ReactNode;
  footer?: ReactNode;
}>) {
  return (
    <main className="auth-shell">
      <div className="auth-layout">
        <aside className="auth-story" aria-label="About OpFin access">
          <Link className="auth-story-brand" href="/" aria-label="OpFin home">
            <span className="auth-story-mark"><OpFinSymbol reverse /></span>
            <span>OpFin</span>
          </Link>
          <div className="auth-story-copy">
            <p className="auth-kicker">ONE IDENTITY · AUTHORISED ACCESS</p>
            <h2>One secure sign-in. The right workspace.</h2>
            <p>
              Personal, group, employer, programme-partner and OpFin team access stay separated by role and Financial Space permissions.
            </p>
          </div>
          <div className="auth-story-points" aria-label="Access principles">
            <span>Private by design</span>
            <span>Role-based access</span>
            <span>Clear next step</span>
          </div>
        </aside>

        <section className="auth-panel">
          <Link className="auth-panel-brand" href="/" aria-label="OpFin home">
            <span className="auth-panel-mark"><OpFinSymbol /></span>
            <span>OpFin</span>
          </Link>
          {eyebrow ? <p className="auth-eyebrow">{eyebrow}</p> : null}
          <h1>{title}</h1>
          <p className="auth-lead">{description}</p>
          <div className="auth-content">{children}</div>
          {footer ? <div className="auth-panel-footer">{footer}</div> : null}
        </section>
      </div>
    </main>
  );
}
