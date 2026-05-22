import type {
  ConsentState,
  DemoConsent,
  DemoDecision,
  DemoOffer,
  Institution,
  InvestorDemoSnapshot,
  LoanApplication,
  LoanProduct,
  ProductTerm,
  Profile,
  RepaymentScheduleRow
} from "./types";

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

export const mockDemoConsent: DemoConsent = {
  id: 1,
  purpose: "credit_processing",
  status: "granted",
  granted_at: "2026-05-21T09:00:00Z",
  metadata: { mock_integration: true }
};

export const mockDemoDecision: DemoDecision = {
  id: 1,
  status: "approved",
  requested_amount_minor: 250000,
  approved_amount_minor: 250000,
  monthly_income_minor: 1200000,
  estimated_monthly_obligation_minor: 83333,
  reason_codes: ["KYC_VERIFIED", "CONSENT_GRANTED", "MOCK_AFFORDABILITY_CHECK", "DEBT_SERVICE_WITHIN_LIMIT"],
  decision_summary: "Approved by mock affordability rules for investor demo only.",
  decided_at: "2026-05-21T09:05:00Z"
};

export const mockDemoOffer: DemoOffer = {
  id: 1,
  status: "pending_acceptance",
  principal_amount_minor: 250000,
  total_repayment_minor: 275000,
  duration_days: 30,
  interest_rate: "10.00",
  interest_type: "Flat",
  repayment_frequency: "Monthly",
  expires_at: "2026-05-22T09:05:00Z",
  accepted_at: null
};

export const mockInvestorSnapshot: InvestorDemoSnapshot = {
  customers: [mockProfile.user],
  applications: mockApplications,
  decisions: [mockDemoDecision],
  offers: [mockDemoOffer],
  loans: [mockApplications[1].loan!],
  ledger_entries: [
    { id: 1, type: "Debit", amount: 250000, reference: "demo-disbursement-1", description: "Loan Disbursement" },
    { id: 2, type: "Credit", amount: 250000, reference: "demo-disbursement-1", description: "Loan Disbursement" }
  ],
  repayment_schedules: mockSchedule,
  mobile_money: [
    {
      id: 1,
      provider: "mock",
      status: "pending",
      direction: "disbursement",
      amount_minor: 250000,
      reconciliation_status: "unreconciled"
    }
  ],
  audit_trail: [
    { id: 1, event: "demo.loan_offer.accepted", created_at: "2026-05-21T09:10:00Z", metadata: { mock_integration: true } }
  ]
};
