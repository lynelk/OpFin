export type UserRole = "customer" | "platform_admin" | "operations" | "support" | "employer_admin";

export type Session = {
  role: UserRole;
  name: string;
};

export type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]>;
};

export type LoginResponse = {
  access_token: string;
  token_type: "Bearer" | string;
  user: {
    id: number;
    name: string;
    phone: string;
    role: UserRole;
    national_id?: string | null;
    date_of_birth?: string | null;
    nin_status?: string | null;
  };
};

export type LoanProduct = {
  id: number;
  name: string;
  status: string;
  institution?: {
    id: number;
    name: string;
  } | null;
};

export type Institution = {
  id: number;
  name: string;
};

export type ProductTerm = {
  id: number;
  loan_product_id: number;
  interest_rate: string | number;
  interest_type: string;
  interest_cycle: string;
  repayment_frequency: string;
  duration: number;
  status: string;
};

export type LoanApplication = {
  id: number;
  amount: string | number;
  status: string;
  reason?: string | null;
  loan_product?: LoanProduct;
  loan_product_term?: ProductTerm;
  loan?: {
    id: number;
    status: string;
    outstanding_balance?: number;
    repayment_start_date?: string | null;
    schedules?: RepaymentScheduleRow[];
  } | null;
};

export type Profile = {
  user: {
    id: number;
    name: string;
    phone: string;
    email?: string | null;
    role: UserRole;
    institution_id?: number | null;
    national_id?: string | null;
    date_of_birth?: string | null;
    nin_status?: string | null;
  };
  permissions: string[];
};

export type RepaymentScheduleRow = {
  id: number;
  due_date: string;
  principal: number;
  interest: number;
  total_outstanding: number;
};

export type ConsentState = {
  status: "sandbox-active" | "sandbox-revoked" | "not-configured";
  label: string;
  updatedAt?: string;
};

export type KycCase = {
  id: number;
  national_id: string;
  provider: string;
  provider_reference?: string | null;
  status: "pending_review" | "verified" | "rejected" | "expired" | string;
  evidence?: Record<string, unknown> | null;
  risk_flags?: string[] | null;
  review_notes?: string | null;
  submitted_at?: string;
  reviewed_at?: string | null;
  expires_at?: string | null;
};

export type ConsentRecord = {
  id: number;
  purpose: string;
  policy_version: string;
  status: "granted" | "revoked" | string;
  channel: string;
  granted_at?: string | null;
  revoked_at?: string | null;
  metadata?: Record<string, unknown> | null;
};

export type DemoDecision = {
  id: number;
  status: "approved" | "declined" | string;
  requested_amount_minor: number;
  approved_amount_minor: number;
  monthly_income_minor: number;
  estimated_monthly_obligation_minor: number;
  reason_codes: string[];
  decision_summary: string;
  decided_at?: string;
};

export type DemoOffer = {
  id: number;
  status: "pending_acceptance" | "accepted" | "expired" | "not-available" | string;
  principal_amount_minor: number;
  total_repayment_minor: number;
  duration_days: number;
  interest_rate: string | number;
  interest_type: string;
  repayment_frequency: string;
  expires_at?: string;
  accepted_at?: string | null;
};

export type DemoConsent = {
  id?: number;
  purpose: string;
  status: "granted" | "revoked" | string;
  granted_at?: string | null;
  revoked_at?: string | null;
  metadata?: Record<string, unknown>;
};

export type DemoDashboard = {
  mock_integrations: string[];
  profile: Profile["user"];
  kyc: {
    status?: string | null;
    national_id?: string | null;
    date_of_birth?: string | null;
    mock_integration: boolean;
  };
  consent?: DemoConsent | null;
  latest_application?: (LoanApplication & {
    demo_decision?: DemoDecision | null;
    demo_offer?: DemoOffer | null;
  }) | null;
};

export type DemoApplicationResult = {
  application: LoanApplication;
  decision: DemoDecision;
  offer: DemoOffer | null;
};

export type DemoOfferAcceptance = {
  offer: DemoOffer;
  loan: NonNullable<LoanApplication["loan"]> & {
    schedules?: RepaymentScheduleRow[];
  };
  mobile_money: {
    id: number;
    provider: string;
    status: string;
    direction: string;
    amount_minor: number;
    reconciliation_status: string;
  };
  ledger_entries: Array<{
    id: number;
    type: string;
    amount: string | number;
    reference: string;
    description: string;
  }>;
  repayment_schedule: RepaymentScheduleRow[];
};

export type InvestorDemoSnapshot = {
  customers: Profile["user"][];
  applications: LoanApplication[];
  decisions: DemoDecision[];
  offers: DemoOffer[];
  loans: NonNullable<LoanApplication["loan"]>[];
  ledger_entries: DemoOfferAcceptance["ledger_entries"];
  repayment_schedules: RepaymentScheduleRow[];
  mobile_money: DemoOfferAcceptance["mobile_money"][];
  audit_trail: Array<{
    id: number;
    event: string;
    created_at: string;
    metadata?: Record<string, unknown>;
  }>;
};
