import Link from "next/link";
import { OpFinSymbol } from "@/components/OpFinSymbol";

const lessons = [
  { title: "Know what comes in and goes out", text: "Start with money you receive, money you spend and the expenses you cannot avoid. Even small amounts are worth understanding.", tip: "Try writing down three things you spent money on this week." },
  { title: "Give your money a purpose", text: "Choose something that matters to you. A goal may be about education, helping family, building an emergency reserve or a future project.", tip: "Name one thing you would like to plan for. It does not have to cost money." },
  { title: "Understand saving and borrowing", text: "Saving means setting money aside. Borrowing means an obligation to repay. Before accepting any loan, understand the full costs, timing and risks.", tip: "Ask: how much will I repay altogether, and when?" },
  { title: "You can recover from a setback", text: "Having less money than expected does not define you. Focus first on essentials, understand what is due and ask for help when needed.", tip: "Choose one manageable action instead of trying to fix everything at once." }
];

export default function LearnPage() {
  return (
    <main className="marketing-shell">
      <header className="marketing-nav">
        <Link className="marketing-brand" href="/" aria-label="OpFin home">
          <span className="marketing-brand-mark"><OpFinSymbol /></span><span>OpFin</span>
        </Link>
        <div className="marketing-nav-actions"><Link className="button secondary" href="/">Back to OpFin</Link></div>
      </header>
      <section className="marketing-hero" style={{ paddingBottom: 24 }}>
        <div className="marketing-hero-copy">
          <p className="marketing-eyebrow">OPEN FINANCIAL LEARNING</p>
          <h1>Every financial life has potential.</h1>
          <p className="marketing-lead">Whatever your age or financial position, you can learn skills that help you make confident money decisions. These lessons do not require registration, an account or eligibility for a financial product.</p>
          <p className="marketing-preview-disclosure">This is general education, not a personalised financial recommendation. Young people should ask a trusted adult when deciding about actual financial products.</p>
        </div>
      </section>
      <section className="content" aria-label="Financial learning topics" style={{ maxWidth: 1100, margin: "0 auto" }}>
        <div className="grid grid-2">
          {lessons.map((lesson, index) => (
            <article className="panel" key={lesson.title}>
              <p className="eyebrow">LESSON {index + 1}</p>
              <h2>{lesson.title}</h2>
              <p>{lesson.text}</p>
              <p className="muted" style={{ marginTop: 12 }}><strong>Try this:</strong> {lesson.tip}</p>
            </article>
          ))}
        </div>
        <section className="panel" style={{ marginTop: 18 }}>
          <h2>Your journey belongs to you</h2>
          <p>Being unable to use a particular financial product does not mean you cannot learn, plan or make progress. Where products have legal, age or provider restrictions, those rules still apply.</p>
          <Link className="button secondary" href="/">Explore OpFin</Link>
        </section>
      </section>
    </main>
  );
}
