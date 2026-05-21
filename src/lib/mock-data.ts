import type { ConsentState, Institution, LoanApplication, LoanProduct, ProductTerm, Profile, RepaymentScheduleRow } from "./types";

export const mockProfile: Profile = {
  user: {
    id: 1,
    name: "Demo Customer",
    phone: "256700000001",
    role: "customer",
    national_id: "CM000000000001",
    date_of_birth: "1994-04-12",
    nin_status: "VALID"
  },
  permissions: ["profile.view"]
};

export const mockProducts: LoanProduct[] = [
  {
    id: 1,
    name: "Salary Advance",
    status: "Active",
    institution: { id: 1, name: "OpFin Demo Institution" }
  },
  {
    id: 2,
    name: "Short Term Loan",
    status: "Active",
    institution: null
  }
];

export const mockInstitutions: Institution[] = [
  { id: 1, name: "OpFin Demo Institution" }
];

export const mockTerms: ProductTerm[] = [
  {
    id: 1,
    loan_product_id: 1,
    interest_rate: "10.00",
    interest_type: "Flat",
    interest_cycle: "Monthly",
    repayment_frequency: "Monthly",
    duration: 30,
    status: "Active"
  }
];

export const mockApplications: LoanApplication[] = [
  {
    id: 101,
    amount: "100000",
    status: "Pending",
    reason: "School fees",
    loan_product: mockProducts[0],
    loan_product_term: mockTerms[0],
    loan: null
  },
  {
    id: 102,
    amount: "250000",
    status: "Disbursed",
    reason: "Household needs",
    loan_product: mockProducts[1],
    loan_product_term: mockTerms[0],
    loan: {
      id: 77,
      status: "Disbursed",
      outstanding_balance: 275000,
      repayment_start_date: "2026-06-20"
    }
  }
];

export const mockSchedule: RepaymentScheduleRow[] = [
  { id: 1, due_date: "2026-06-20", principal: 125000, interest: 12500, total_outstanding: 137500 },
  { id: 2, due_date: "2026-07-20", principal: 125000, interest: 12500, total_outstanding: 137500 }
];

export const mockAuditEvents = [
  { id: 1, event: "profile.viewed", created_at: "2026-05-21 09:00", actor: "support" },
  { id: 2, event: "loan_application.status_updated", created_at: "2026-05-21 10:12", actor: "operations" },
  { id: 3, event: "mobile_money.collection.requested", created_at: "2026-05-21 11:30", actor: "system" }
];

export const mockConsentState: ConsentState = {
  status: "not-configured",
  label: "Consent API contract pending"
};
