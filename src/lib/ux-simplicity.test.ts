import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

function source(path: string): string {
  return readFileSync(resolve(process.cwd(), path), "utf8");
}

describe("OpFin simplicity contract", () => {
  it("keeps conventional credit application customer-led instead of configuration-led", () => {
    const page = source("src/app/(portal)/loans/apply/page.tsx");
    expect(page).toContain("How much do you need?");
    expect(page).toContain("What do you need it for?");
    expect(page).not.toContain('name="loan_product_id"');
    expect(page).not.toContain('name="institution_id"');
    expect(page).not.toContain('name="loan_product_term_id"');
  });

  it("keeps peer-borrower governance fields out of the customer form", () => {
    const page = source("src/app/(portal)/peer-lending/borrow/page.tsx");
    expect(page).toContain("How much do you need?");
    expect(page).toContain("What is the money for?");
    expect(page).toContain("How long do you need?");
    for (const internalField of ["lender_of_record", "loss_allocation", "custody", "expected_return_percent", "risk_grade"]) {
      expect(page).not.toContain(`name="${internalField}"`);
    }
  });

  it("puts investor decision information before internal settlement detail", () => {
    const page = source("src/app/(portal)/peer-lending/page.tsx");
    expect(page).toContain("Expected return");
    expect(page).toContain("Risk");
    expect(page).toContain("Term");
    expect(page).toContain("Repayment");
    expect(page).toContain("funded");
    expect(page).toContain("Risk & full marketplace disclosures");
  });

  it("makes connected financial life a hub instead of a mega-form", () => {
    const page = source("src/app/(portal)/ecosystem/page.tsx");
    expect(page).toContain("Each task has its own focused journey");
    expect(page).not.toContain("<form");
  });

  it("keeps peer-lending legal and risk configuration in operations", () => {
    const page = source("src/app/(portal)/admin/long-range/page.tsx");
    expect(page).toContain("Responsible lender of record");
    expect(page).toContain("Expected return (%)");
    expect(page).toContain("Risk grade");
    expect(page).toContain("Loss treatment");
    expect(page).toContain("Custody / settlement");
  });

  it("keeps web and Flutter on the same five-part customer mental model", () => {
    const mobile = source("opfin-frontend/lib/home_screen.dart");
    for (const label of ["Home", "Borrow", "Save", "Grow", "More"]) {
      expect(mobile).toContain(`label: '${label}'`);
    }
    expect(mobile).not.toContain("label: 'Connected'");
    expect(mobile).not.toContain("label: 'Profile'");
    expect(mobile).not.toContain("label: 'Help'");
  });
});
