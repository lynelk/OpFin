import type { Metadata } from "next";
import type { ReactNode } from "react";
import "./globals.css";
import "./marketing.css";

export const metadata: Metadata = {
  title: "OpFin | Build a stronger financial life",
  description: "Understand your money, build savings, access responsible credit, protect what matters and grow your future with OpFin."
};

export default function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
