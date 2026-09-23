import type { Metadata } from "next";
import type { ReactNode } from "react";
import { connection } from "next/server";
import "./brand-tokens.css";
import "./globals.css";
import "./marketing.css";
import "./experience.css";
import "./opfin-brand.css";

export const metadata: Metadata = {
  title: "OpFin | Understand, manage, plan and improve your money",
  description:
    "OpFin connects everyday money, financial health, responsible credit, savings, protection and partner services across personal, group and organisation Financial Spaces. Services remain subject to eligibility, provider activation and availability.",
};

export default async function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  await connection();
  return (
    <html lang="en-GB">
      <body>{children}</body>
    </html>
  );
}
