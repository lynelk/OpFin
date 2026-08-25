export type CapabilityStatus = "AVAILABLE" | "PILOT" | "SANDBOX" | "PLANNED" | "DISABLED";

export type CapabilityDefinition = {
  status: CapabilityStatus;
  owner: "opfin" | "cpay" | string;
};

export type CountryPolicy = {
  name: string;
  status: CapabilityStatus;
  currency: string;
  languages: string[];
  payment_platform: string;
  payment_status: CapabilityStatus;
};

export type CapabilityRegistry = {
  country: string;
  country_policy: CountryPolicy;
  capabilities: Record<string, CapabilityDefinition>;
};

export function capabilityIsUsable(capability?: CapabilityDefinition): boolean {
  return capability?.status === "AVAILABLE" || capability?.status === "PILOT" || capability?.status === "SANDBOX";
}

export function capabilityLabel(capability?: CapabilityDefinition): string {
  if (!capability) return "Unavailable";
  return capability.status.charAt(0) + capability.status.slice(1).toLowerCase();
}
