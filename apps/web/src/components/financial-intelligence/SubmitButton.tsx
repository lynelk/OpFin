"use client";
import { useFormStatus } from "react-dom";
export function SubmitButton({ children }: { children: React.ReactNode }) {
  const { pending } = useFormStatus();
  return <button className="button" type="submit" disabled={pending} aria-disabled={pending}>{pending ? "Submitting…" : children}</button>;
}
