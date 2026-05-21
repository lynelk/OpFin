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

export type LoanProduct = {
  id: number;
  name: string;
  status: string;
  institution?: {
    id: number;
    name: string;
  } | null;
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
    role: UserRole;
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
