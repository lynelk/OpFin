import type { ReactNode } from "react";

export function Screen({
  title,
  description,
  action,
  children
}: Readonly<{
  title: string;
  description: string;
  action?: ReactNode;
  children: ReactNode;
}>) {
  return (
    <>
      <div className="page-head">
        <div>
          <h1>{title}</h1>
          <p>{description}</p>
        </div>
        {action}
      </div>
      {children}
    </>
  );
}

export function PlaceholderPanel({ title, text }: Readonly<{ title: string; text: string }>) {
  return (
    <section className="panel">
      <h2>{title}</h2>
      <div className="placeholder">{text}</div>
    </section>
  );
}
