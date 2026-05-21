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

export type DemoDecision = {
  status: string;
  message: string;
  source: "backend-application-status" | "sandbox";
};

export type DemoOffer = {
  status: "sandbox-offer" | "not-available";
  message: string;
};
