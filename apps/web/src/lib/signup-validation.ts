export function normaliseUgandanPhone(raw: string): string | null {
  const phone = raw.replace(/[\s()-]/g, "");
  if (/^07\d{8}$/.test(phone)) return "256" + phone.slice(1);
  if (/^\+2567\d{8}$/.test(phone)) return phone.slice(1);
  if (/^2567\d{8}$/.test(phone)) return phone;
  return null;
}
export function validSignupPin(pin: string, confirmation: string): boolean {
  if (!/^\d{6}$/.test(pin) || pin !== confirmation) return false;
  return !/^(\d)\1{5}$/.test(pin) && !["012345","123456","234567","345678","456789","987654","876543","765432","654321","543210"].includes(pin);
}
