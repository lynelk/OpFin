import type {
  ProtectionClaim,
  ProtectionPolicy,
  ProtectionPremiumPayment,
  ProtectionProduct,
  SavingsMovement,
  SavingsProduct
} from "./types";

export type ApprovalEvidence = {
  approval_reference: string;
  approval_evidence_hash: string;
  approval_note: string;
  approved_at?: string;
};

export type AdminSavingsProduct = SavingsProduct & {
  status: "draft" | "active" | "paused" | "retired" | string;
  created_by?: number | null;
  approved_by?: number | null;
  approved_at?: string | null;
  approval_evidence?: ApprovalEvidence | null;
  effective_at?: string | null;
  expires_at?: string | null;
};

export type AdminProtectionProduct = ProtectionProduct & {
  status: "draft" | "active" | "paused" | "retired" | string;
  created_by?: number | null;
  approved_by?: number | null;
  approved_at?: string | null;
  approval_evidence?: ApprovalEvidence | null;
  effective_at?: string | null;
  expires_at?: string | null;
};

export type SavingsQueueMovement = SavingsMovement & {
  goal?: {
    id: number;
    name: string;
    goal_reference: string;
    product?: SavingsProduct;
  };
};

export type PremiumQueuePayment = ProtectionPremiumPayment & {
  policy?: ProtectionPolicy;
};

export type ClaimQueueItem = ProtectionClaim & {
  policy?: ProtectionPolicy;
};

export type SaveProtectionWorkQueue = {
  counts: {
    savings_contributions: number;
    savings_withdrawals: number;
    protection_premiums: number;
    protection_policies: number;
    protection_claims: number;
  };
  savings_contributions: SavingsQueueMovement[];
  savings_withdrawals: SavingsQueueMovement[];
  protection_premiums: PremiumQueuePayment[];
  protection_policies: ProtectionPolicy[];
  protection_claims: ClaimQueueItem[];
  scope: "institution" | "platform";
  institution_id?: number | null;
};

export type SavingsProductDraftInput = {
  code: string;
  name: string;
  partner_name: string;
  partner_product_reference?: string;
  country_code: string;
  currency: string;
  product_type: "goal" | "emergency" | "notice" | "group" | "sacco" | "employer";
  custody_model: "partner_held";
  minimum_contribution_minor?: number;
  maximum_contribution_minor?: number;
  minimum_withdrawal_minor?: number;
  notice_days?: number;
  lock_days?: number;
  terms_version: string;
  terms_url?: string;
  disclosures?: string[];
};

export type ProtectionProductDraftInput = {
  code: string;
  name: string;
  insurer_name: string;
  underwriter_name?: string;
  partner_product_reference?: string;
  country_code: string;
  currency: string;
  product_type: "micro" | "loan" | "health" | "event" | "device" | "asset";
  premium_amount_minor: number;
  premium_frequency: "weekly" | "monthly" | "quarterly" | "annual" | "yearly" | "one_off" | "single";
  coverage_limit_minor?: number;
  disclosure_version: string;
  benefits?: string[];
  exclusions?: string[];
  disclosure_payload: Record<string, unknown>;
  terms_url?: string;
};
