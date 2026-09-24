import { OpFinSymbol } from "@/components/OpFinSymbol";
import Link from "next/link";

const audiences = [
  { title: "Individuals", text: "Understand everyday money, budgets, goals, debt, assets and available financial services from one connected picture." },
  { title: "Savings groups", text: "Create or join a group, manage members, contributions, obligations and shared progress with mobile-first journeys." },
  { title: "Businesses & employers", text: "Separate organisation finances, enable employer services and use Web Workspaces for deeper administration." },
  { title: "SACCOs & partners", text: "Connect governed products, programmes, members and reporting through Workspaces, APIs and partner capabilities." },
];

const pillars = [
  { label: "Manage", title: "See your money clearly", text: "Bring cash flow, obligations, assets and goals into one Financial Compass." },
  { label: "Save", title: "Build resilience", text: "Create goals and use available partner services without pretending an unavailable provider is live." },
  { label: "Protect", title: "Cover what matters", text: "Review suitable protection, disclosures, premiums and claims through activated partner journeys." },
  { label: "Borrow", title: "Access responsible credit", text: "See transparent costs, affordability, decision reasons and obligations before accepting an offer." },
  { label: "Grow", title: "Build long-term progress", text: "Use verified financial history to support suitable future investment and wealth journeys." },
];

const steps = [
  ["01", "Join securely", "New customers start in the App with phone verification, names and a six-digit PIN."],
  ["02", "Understand your position", "OpFin separates recorded facts, estimates and unavailable information."],
  ["03", "Take the next useful step", "Manage, save, borrow, protect or grow according to your goals and available services."],
  ["04", "Progress over time", "Build a stronger verified financial record without turning programme data into hidden underwriting."],
];

const portalWorkspaces = [
  {
    eyebrow: "PERSONAL",
    title: "Personal & Financial Spaces",
    text: "Your personal dashboard plus authorised household, savings-group, investment-club, business and SACCO spaces. Programme check-ins stay inside the personal experience.",
    href: "/login?context=personal",
    action: "Sign in to Personal"
  },
  {
    eyebrow: "EMPLOYER",
    title: "Employer Workspace",
    text: "Verified employer administration and financial-wellbeing programmes for authorised employer roles.",
    href: "/login?context=employer",
    action: "Sign in to Employer"
  },
  {
    eyebrow: "PROGRAMME PARTNER",
    title: "Programme Partner Workspace",
    text: "Programme-scoped aggregate impact and service-network reporting for assigned partner accounts.",
    href: "/login?context=partner",
    action: "Sign in to Partner"
  },
  {
    eyebrow: "OPFIN TEAM",
    title: "OpFin Operations",
    text: "Operations, support, compliance, inclusion, programme delivery, impact and commercial tools, filtered by staff role.",
    href: "/login?context=operations",
    action: "Sign in to Operations"
  }
];

export default function HomePage() {
  return (
    <main className="marketing-shell">
      <header className="marketing-nav">
        <Link className="marketing-brand" href="/" aria-label="OpFin home">
          <span className="marketing-brand-mark"><OpFinSymbol /></span>
          <span>OpFin</span>
        </Link>
        <nav className="marketing-nav-links" aria-label="Primary">
          <a href="#individuals">Individuals</a>
          <a href="#groups">Groups</a>
          <a href="#employers">Businesses</a>
          <a href="#partners">Partners</a>
          <a href="#access">Access</a>
          <a href="#learn">Learn</a>
        </nav>
        <div className="marketing-nav-actions">
          <Link className="button marketing-primary" href="/login">Sign in to Web</Link>
        </div>
      </header>

      <section className="marketing-hero" id="individuals">
        <div className="marketing-hero-copy">
          <p className="marketing-eyebrow">YOUR FINANCIAL PROGRESS, CONNECTED</p>
          <h1>Understand, manage, plan and improve your money.</h1>
          <p className="marketing-lead">
            One OpFin identity can connect your personal money, savings groups and authorised organisations while keeping each Financial Space appropriately separated.
          </p>
          <div className="marketing-hero-actions">
            <Link className="button marketing-primary marketing-large" href="/login">Sign in to Web</Link>
            <a className="button secondary marketing-large" href="#how-it-works">Explore OpFin</a>
          </div>
          <p className="marketing-preview-disclosure">
            New-customer registration is phone-first in the OpFin App. Web sign-in is for existing or authorised Workspace access.
          </p>
          <div className="marketing-trust-row" aria-label="OpFin trust commitments">
            <span>✓ Secure by design</span>
            <span>✓ Transparent decisions</span>
            <span>✓ Permission-led data use</span>
          </div>
        </div>

        <div className="marketing-product-stage" aria-label="Illustrative OpFin product preview, not a production screenshot">
          <p className="marketing-preview-disclosure">Illustrative preview. Services depend on eligibility, provider activation and availability.</p>
          <div className="marketing-orbit marketing-orbit-one" />
          <div className="marketing-orbit marketing-orbit-two" />
          <div className="marketing-phone">
            <div className="marketing-phone-top"><span>OpFin</span><span className="marketing-avatar">OF</span></div>
            <p className="marketing-small">Your financial compass</p>
            <h2>Your next useful step</h2>
            <div className="marketing-next-card">
              <span className="badge ok">On track</span>
              <strong>Build your emergency fund</strong>
              <p>Use recorded information to plan the next step. Estimates stay labelled as estimates.</p>
              <span className="marketing-mini-action">Review plan →</span>
            </div>
            <div className="marketing-money-grid">
              <div><span>Available</span><strong>Recorded</strong></div>
              <div><span>Committed</span><strong>Tracked</strong></div>
            </div>
            <div className="marketing-app-actions">
              <span>Manage</span><span>Borrow</span><span>Save</span><span>Protect</span>
            </div>
          </div>
        </div>
      </section>

      <section className="marketing-proof-strip" aria-label="OpFin platform strengths">
        <div><strong>One identity</strong><span>across authorised Financial Spaces</span></div>
        <div><strong>One financial record</strong><span>with provenance and permission</span></div>
        <div><strong>One next action</strong><span>instead of a wall of products</span></div>
        <div><strong>Human control</strong><span>for high-impact decisions</span></div>
      </section>

      <section className="marketing-section" id="groups">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">ONE IDENTITY, MANY FINANCIAL SPACES</p>
          <h2>Keep personal, group and organisation finances connected without mixing authority or privacy.</h2>
          <p>Individuals and savings groups are designed for complete mobile journeys. Web adds analysis, programme, partner and institutional workspace depth.</p>
        </div>
        <div className="marketing-pillar-grid">
          {audiences.map((audience) => (
            <article className="marketing-feature-card" key={audience.title}>
              <h3>{audience.title}</h3><p>{audience.text}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="marketing-section" id="how-it-works">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">EVERYTHING WORKS TOGETHER</p>
          <h2>A financial operating platform organised around the jobs your money needs to do.</h2>
          <p>Financial services remain subject to eligibility, suitability, regulatory requirements and genuine provider availability.</p>
        </div>
        <div className="marketing-pillar-grid">
          {pillars.map((pillar) => (
            <article className="marketing-feature-card" key={pillar.label}>
              <span className="marketing-feature-label">{pillar.label}</span>
              <h3>{pillar.title}</h3>
              <p>{pillar.text}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="marketing-section marketing-dark-section">
        <div className="marketing-section-head marketing-section-head-light">
          <p className="marketing-eyebrow">RESPONSIBLE CREDIT</p>
          <h2>Borrow with the full picture in front of you.</h2>
          <p>Eligibility, affordability, total repayment, fees, tenure and decision reasons should be visible before acceptance. A provider request is not complete money movement until confirmed.</p>
        </div>
        <div className="marketing-dark-grid">
          <article><span>01</span><h3>Check eligibility</h3><p>Use verified identity, consent and governed financial signals.</p></article>
          <article><span>02</span><h3>Understand the offer</h3><p>See principal, cost, repayment and disclosures together.</p></article>
          <article><span>03</span><h3>Confirm money movement</h3><p>Track pending, final and reconciled states accurately.</p></article>
          <article><span>04</span><h3>Keep progressing</h3><p>Build a stronger verified record from actual behaviour.</p></article>
        </div>
      </section>

      <section className="marketing-section" id="employers">
        <div className="marketing-enterprise-card">
          <div>
            <p className="marketing-eyebrow">OPFIN WORK</p>
            <h2>Financial wellbeing for modern employers.</h2>
            <p>Support employee financial health with permission-led services without turning HR into a lending desk or exposing private Personal Space data.</p>
          </div>
          <Link className="button marketing-primary" href="/login">Employer access</Link>
        </div>
      </section>

      <section className="marketing-section" id="partners">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">PROGRAMMES & PARTNER ECOSYSTEM</p>
          <h2>Governed rails for providers, inclusive-finance programmes and institutional partners.</h2>
          <p>
            OpFin supports programme instruments, consented outcome measurement, privacy-suppressed partner reporting and provider integrations without treating programme demographics or impact observations as hidden credit inputs.
          </p>
        </div>
      </section>

      <section className="marketing-section" id="learn">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">HOW PROGRESS WORKS</p>
          <h2>Start with the financial job that matters now. Build from there.</h2>
        </div>
        <div className="marketing-step-grid">
          {steps.map(([number, title, copy]) => (
            <article key={number}><span>{number}</span><h3>{title}</h3><p>{copy}</p></article>
          ))}
        </div>
      </section>

      <section className="marketing-section" id="access">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">PORTAL ACCESS</p>
          <h2>One sign-in. The workspace follows your authorised role.</h2>
          <p>
            OpFin keeps Financial Spaces connected under one identity while separating institutional and staff authority. The public site therefore exposes a small number of workspace entry points rather than a separate login for every module.
          </p>
        </div>
        <div className="portal-access-grid">
          {portalWorkspaces.map((workspace) => (
            <article className="portal-access-card" key={workspace.title}>
              <p className="marketing-eyebrow">{workspace.eyebrow}</p>
              <h3>{workspace.title}</h3>
              <p>{workspace.text}</p>
              <Link className="button secondary" href={workspace.href}>{workspace.action}</Link>
            </article>
          ))}
        </div>
        <p className="portal-access-note">
          Support, inclusion & programmes, impact framework, programme delivery and commercial performance are role-gated modules inside OpFin Operations, not separate authentication portals. First-time programme partners activate an authorised invitation before using the same sign-in.
        </p>
      </section>

      <section className="marketing-final-cta">
        <p className="marketing-eyebrow">EXISTING CUSTOMER OR AUTHORISED USER</p>
        <h2>Continue in OpFin Web.</h2>
        <p>New customers start with phone verification in the App. Existing and authorised users can continue to Web or Workspace access.</p>
        <Link className="button marketing-primary marketing-large" href="/login">Sign in to Web</Link>
      </section>

      <footer className="marketing-footer">
        <div className="marketing-brand"><span className="marketing-brand-mark"><OpFinSymbol /></span><span>OpFin</span></div>
        <p>Financial wellbeing, access and progression in one connected platform.</p>
        <div><Link href="/login">Sign in</Link><a href="#individuals">Individuals</a><a href="#employers">Employers</a><a href="#partners">Partners</a></div>
        <div className="marketing-legal-links"><a href="https://opfin-production.up.railway.app/privacy-policy">Privacy policy</a><Link href="/account/delete">Delete account</Link></div>
      </footer>
    </main>
  );
}
