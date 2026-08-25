export type PaymentMovement = {
  id: number;
  provider: string;
  status: string;
  direction: string;
  amount_minor: number;
  currency: string;
  provider_reference?: string | null;
  internal_reference?: string | null;
  reconciliation_status?: string | null;
};

export type SavingsProduct = {
  id: number;
  code: string;
  name: string;
  partner_name: string;
  partner_product_reference?: string | null;
  country_code: string;
  currency: string;
  product_type: string;
  status?: string;
  custody_model: "partner_held" | string;
  minimum_contribution_minor: number;
  maximum_contribution_minor?: number | null;
  minimum_withdrawal_minor: number;
  notice_days: number;
  lock_days: number;
  terms_version: string;
  terms_url?: string | null;
  disclosures?: string[] | null;
};

export type SavingsGoal = {
  id: number;
  goal_reference: string;
  name: string;
  status: "active" | "paused" | "completed" | "closed" | string;
  target_amount_minor?: number | null;
  target_date?: string | null;
  confirmed_balance_minor: number;
  reserved_withdrawal_minor: number;
  available_balance_minor: number;
  scheduled_amount_minor?: number | null;
  contribution_frequency?: string | null;
  autopilot_enabled: boolean;
  product: SavingsProduct;
};

export type SavingsMovement = {
  id: number;
  savings_goal_id: number;
  mobile_money_transaction_id?: number | null;
  movement_reference: string;
  movement_type: "contribution" | "withdrawal" | string;
  status: string;
  amount_minor: number;
  currency: string;
  partner_reference?: string | null;
  requested_at: string;
  provider_completed_at?: string | null;
  partner_confirmed_at?: string | null;
  completed_at?: string | null;
  metadata?: Record<string, unknown> | null;
  mobile_money_transaction?: PaymentMovement | null;
};

export type ProtectionProduct = {
  id: number;
  code: string;
  name: string;
  insurer_name: string;
  underwriter_name?: string | null;
  country_code: string;
  currency: string;
  product_type: string;
  premium_amount_minor: number;
  premium_frequency: string;
  coverage_limit_minor?: number | null;
  benefits?: string[] | null;
  exclusions?: string[] | null;
  disclosure_version: string;
  disclosure_payload: Record<string, unknown>;
  terms_url?: string | null;
  disclosure_hash: string;
};

export type ProtectionPolicyProduct = Omit<ProtectionProduct, "disclosure_hash"> & {
  disclosure_hash?: string;
};

export type ProtectionPremiumPayment = {
  id: number;
  protection_policy_id: number;
  mobile_money_transaction_id?: number | null;
  payment_reference: string;
  status: string;
  amount_minor: number;
  currency: string;
  coverage_period_start?: string | null;
  coverage_period_end?: string | null;
  partner_reference?: string | null;
  requested_at: string;
  provider_completed_at?: string | null;
  partner_confirmed_at?: string | null;
  metadata?: Record<string, unknown> | null;
  mobile_money_transaction?: PaymentMovement | null;
};

export type ProtectionClaim = {
  id: number;
  protection_policy_id: number;
  claim_reference: string;
  partner_claim_reference?: string | null;
  status: "submitted" | "partner_review" | "approved" | "declined" | "paid" | "disputed" | "closed" | string;
  incident_date: string;
  category: string;
  description: string;
  claimed_amount_minor?: number | null;
  approved_amount_minor?: number | null;
  evidence?: string[] | null;
  decision_reason?: string | null;
  submitted_at: string;
  resolved_at?: string | null;
};

export type ProtectionPolicy = {
  id: number;
  protection_product_id: number;
  policy_reference: string;
  external_policy_number?: string | null;
  partner_reference?: string | null;
  status: "premium_due" | "premium_pending" | "pending_issuance" | "active" | "lapsed" | "cancelled" | "expired" | string;
  premium_amount_minor: number;
  premium_frequency: string;
  coverage_limit_minor?: number | null;
  cover_start_date?: string | null;
  cover_end_date?: string | null;
  next_premium_due_date?: string | null;
  disclosure_hash: string;
  enrolled_at: string;
  issued_at?: string | null;
  cancelled_at?: string | null;
  product: ProtectionPolicyProduct;
  premium_payments?: ProtectionPremiumPayment[];
  claims?: ProtectionClaim[];
};

export type SavingsProductsPayload = {
  products: SavingsProduct[];
  custody_notice: string;
};

export type SavingsGoalsPayload = {
  goals: SavingsGoal[];
};

export type SavingsGoalPayload = {
  goal: SavingsGoal;
  movements?: SavingsMovement[];
};

export type SavingsMovementPayload = {
  movement: SavingsMovement;
  position_state?: string;
  next_state?: string;
};

export type ProtectionProductsPayload = {
  products: ProtectionProduct[];
  risk_notice: string;
};

export type ProtectionPoliciesPayload = {
  policies: ProtectionPolicy[];
};

export type ProtectionPolicyPayload = {
  policy: ProtectionPolicy;
};

export type ProtectionEnrollmentPayload = {
  policy: ProtectionPolicy;
  next_state: string;
};

export type ProtectionPremiumPayload = {
  premium_payment: ProtectionPremiumPayment;
  policy: ProtectionPolicy;
  next_state: string;
};

export type ProtectionClaimPayload = {
  claim: ProtectionClaim;
  decision_authority?: string;
};
