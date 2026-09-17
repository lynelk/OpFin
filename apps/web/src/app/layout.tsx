import type { Metadata } from "next";
import type { ReactNode } from "react";
import { connection } from "next/server";
import "./brand-tokens.css";
import "./globals.css";
import "./marketing.css";
import "./experience.css";
import "./opfin-brand.css";

export const metadata: Metadata = {
  title: "OpFin | Your next step, clearer.",
  description: "Explore available credit, understand your obligations and take your next financial step with OpFin. Services are subject to eligibility and availability.",
};

export default async function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  // Each HTML response receives a fresh CSP nonce. Do not statically cache it.
  await connection();
  return (
    <html lang="en-GB">
      <body>{children}</body>
    </html>
  );
}
