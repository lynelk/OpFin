import Link from "next/link";

const pillars = [
  { label: "Manage", title: "See your money clearly", text: "Bring accounts, cash flow, obligations and goals into one Financial Compass." },
  { label: "Save", title: "Build resilience automatically", text: "Create goals, contribute on your schedule and let approved rules keep progress moving." },
  { label: "Protect", title: "Cover what matters", text: "Discover suitable protection, understand the terms and manage premiums and claims in one place." },
  { label: "Borrow", title: "Access responsible credit", text: "See transparent costs, explainable decisions and repayment obligations before you accept an offer." },
  { label: "Grow", title: "Move toward long-term goals", text: "Build a verified financial record that can support future investment and wealth journeys." }
];

const steps = [
  ["01", "Join securely", "Verify your phone, protect your account and keep control of your permissions."],
  ["02", "Understand your position", "OpFin turns recorded financial information into a practical Financial Compass."],
  ["03", "Take the next best step", "Save, borrow, protect or grow based on your goals and current position."],
  ["04", "Progress over time", "Your Financial Passport becomes more useful as verified behaviour and history accumulate."]
];

export default function HomePage() {
  return (
    <main className="marketing-shell">
      <header className="marketing-nav">
        <Link className="marketing-brand" href="/" aria-label="OpFin home">
          <span className="marketing-brand-mark">O</span>
          <span>OpFin</span>
        </Link>
        <nav className="marketing-nav-links" aria-label="Primary">
          <a href="#individuals">Individuals</a>
          <a href="#employers">Employers</a>
          <a href="#partners">Partners</a>
          <a href="#learn">Learn</a>
        </nav>
        <div className="marketing-nav-actions">
          <Link className="marketing-text-link" href="/login">Sign in</Link>
          <Link className="button marketing-primary" href="/login">Get started</Link>
        </div>
      </header>

      <section className="marketing-hero" id="individuals">
        <div className="marketing-hero-copy">
          <p className="marketing-eyebrow">YOUR FINANCIAL PROGRESS, CONNECTED</p>
          <h1>One place to move your money forward.</h1>
          <p className="marketing-lead">Understand your money, build savings, access responsible credit, protect what matters and grow your future with a financial platform that gets smarter as you progress.</p>
          <div className="marketing-hero-actions">
            <Link className="button marketing-primary marketing-large" href="/login">Get started</Link>
            <a className="button secondary marketing-large" href="#how-it-works">Explore OpFin</a>
          </div>
          <div className="marketing-trust-row" aria-label="OpFin trust commitments">
            <span>✓ Secure by design</span>
            <span>✓ Transparent decisions</span>
            <span>✓ Permission-led data use</span>
          </div>
        </div>

        <div className="marketing-product-stage" aria-label="OpFin product preview">
          <div className="marketing-orbit marketing-orbit-one" />
          <div className="marketing-orbit marketing-orbit-two" />
          <div className="marketing-phone">
            <div className="marketing-phone-top"><span>OpFin</span><span className="marketing-avatar">LK</span></div>
            <p className="marketing-small">Good evening</p>
            <h2>Your next best step</h2>
            <div className="marketing-next-card">
              <span className="badge ok">On track</span>
              <strong>Build your emergency fund</strong>
              <p>Save UGX 45,000 this week to stay on pace for your target.</p>
              <span className="marketing-mini-action">Add to savings →</span>
            </div>
            <div className="marketing-money-grid">
              <div><span>Available</span><strong>UGX 620k</strong></div>
              <div><span>Committed</span><strong>UGX 185k</strong></div>
            </div>
            <div className="marketing-app-actions">
              <span>Borrow</span><span>Save</span><span>Grow</span><span>Protect</span>
            </div>
          </div>
        </div>
      </section>

      <section className="marketing-proof-strip" aria-label="OpFin platform strengths">
        <div><strong>One relationship</strong><span>across everyday financial needs</span></div>
        <div><strong>One Financial Passport</strong><span>with provenance and permission</span></div>
        <div><strong>One next best action</strong><span>instead of a wall of products</span></div>
        <div><strong>Human control</strong><span>for high-impact decisions</span></div>
      </section>

      <section className="marketing-section" id="how-it-works">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">EVERYTHING WORKS TOGETHER</p>
          <h2>A financial platform organised around your life, not product silos.</h2>
          <p>OpFin connects financial wellbeing, savings, protection and responsible credit so each service can improve the next decision.</p>
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

      <section className="marketing-section marketing-split-section">
        <div>
          <p className="marketing-eyebrow">FINANCIAL COMPASS</p>
          <h2>Know where you stand and what to do next.</h2>
          <p className="marketing-lead-sm">OpFin combines recorded balances, commitments, goals and upcoming events into a practical view of your position. It separates confirmed information from estimates instead of pretending uncertainty is a feature.</p>
          <ul className="marketing-check-list">
            <li>Available money and committed obligations</li>
            <li>Safe-to-spend guidance with source confidence</li>
            <li>Upcoming financial calendar</li>
            <li>One prioritised next action</li>
          </ul>
        </div>
        <div className="marketing-compass-card">
          <div className="marketing-compass-score"><span>Financial position</span><strong>Improving</strong></div>
          <div className="marketing-progress"><span style={{ width: "72%" }} /></div>
          <div className="marketing-compass-grid">
            <div><span>Cash flow</span><strong>Positive</strong></div>
            <div><span>Emergency savings</span><strong>1.4 months</strong></div>
            <div><span>Debt obligations</span><strong>Manageable</strong></div>
            <div><span>Next review</span><strong>7 days</strong></div>
          </div>
        </div>
      </section>

      <section className="marketing-section marketing-dark-section">
        <div className="marketing-section-head marketing-section-head-light">
          <p className="marketing-eyebrow">RESPONSIBLE CREDIT</p>
          <h2>Borrow with the full picture in front of you.</h2>
          <p>Eligibility, affordability, total repayment, fees, tenure and decision reasons should be visible before acceptance. OpFin is designed to make a loan understandable before it becomes an obligation.</p>
        </div>
        <div className="marketing-dark-grid">
          <article><span>01</span><h3>Check eligibility</h3><p>Use verified identity, consent and relevant financial signals.</p></article>
          <article><span>02</span><h3>Understand the offer</h3><p>See principal, cost, repayment and disclosures together.</p></article>
          <article><span>03</span><h3>Repay with clarity</h3><p>Track schedules, confirmed payments and reconciliation status.</p></article>
          <article><span>04</span><h3>Keep progressing</h3><p>Use good repayment behaviour as part of a stronger financial record.</p></article>
        </div>
      </section>

      <section className="marketing-section" id="autopilot">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">OPFIN AUTOPILOT</p>
          <h2>Quiet automation for the routine. Human judgment where it matters.</h2>
          <p>OpFin monitors operational journeys, handles safe repeatable tasks under policy and sends only genuine exceptions to people.</p>
        </div>
        <div className="marketing-autopilot-grid">
          <article><strong>Observe</strong><p>Monitor onboarding, consent, payments, reconciliation, support and risk signals.</p></article>
          <article><strong>Decide</strong><p>Use versioned rules and workflows with explicit autonomy tiers.</p></article>
          <article><strong>Act</strong><p>Execute authorised reversible actions and preserve full audit evidence.</p></article>
          <article><strong>Escalate</strong><p>Route ambiguous or high-impact decisions to the right human queue.</p></article>
        </div>
      </section>

      <section className="marketing-section" id="employers">
        <div className="marketing-enterprise-card">
          <div>
            <p className="marketing-eyebrow">OPFIN WORK</p>
            <h2>Financial wellbeing for modern employers.</h2>
            <p>Support employee financial health with permission-led verification, responsible access and employer-linked benefits without turning HR into a lending desk.</p>
          </div>
          <Link className="button marketing-primary" href="/login">Employer access</Link>
        </div>
      </section>

      <section className="marketing-section" id="partners">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">PARTNER ECOSYSTEM</p>
          <h2>One governed rail for providers, institutions and future marketplaces.</h2>
          <p>OpFin is designed to connect licensed financial providers through explicit capabilities, transparent customer consent and auditable money movement.</p>
        </div>
      </section>

      <section className="marketing-section" id="learn">
        <div className="marketing-section-head">
          <p className="marketing-eyebrow">HOW PROGRESS WORKS</p>
          <h2>Start small. Build a stronger financial record over time.</h2>
        </div>
        <div className="marketing-step-grid">
          {steps.map(([number, title, text]) => (
            <article key={number}><span>{number}</span><h3>{title}</h3><p>{text}</p></article>
          ))}
        </div>
      </section>

      <section className="marketing-final-cta">
        <p className="marketing-eyebrow">YOUR NEXT STEP</p>
        <h2>Build a stronger financial life.</h2>
        <p>Start with the goal that matters most. OpFin will help organise the journey from there.</p>
        <Link className="button marketing-primary marketing-large" href="/login">Get started</Link>
      </section>

      <footer className="marketing-footer">
        <div className="marketing-brand"><span className="marketing-brand-mark">O</span><span>OpFin</span></div>
        <p>Financial wellbeing, access and progression in one connected platform.</p>
        <div><Link href="/login">Sign in</Link><a href="#individuals">Individuals</a><a href="#employers">Employers</a><a href="#partners">Partners</a></div>
      </footer>
    </main>
  );
}
