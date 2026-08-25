import Link from "next/link";

export function JourneyCard({
  title,
  description,
  href,
  action,
  status
}: Readonly<{
  title: string;
  description: string;
  href: string;
  action: string;
  status?: "available" | "pilot" | "planned";
}>) {
  return (
    <section className="panel journey-card">
      <div className="journey-card-head">
        <h2>{title}</h2>
        {status ? <span className={`badge ${status === "available" ? "ok" : "warn"}`}>{status}</span> : null}
      </div>
      <p className="muted">{description}</p>
      <Link className="button secondary journey-card-action" href={href}>
        {action}
      </Link>
    </section>
  );
}
