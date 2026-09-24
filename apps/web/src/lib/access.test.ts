import { describe, expect, it } from "vitest";
import { canRoleOpenPath, homeForRole, isProtectedPortalPath } from "./access";

describe("role-aware workspace access", () => {
  it("routes each role to its own workspace", () => {
    expect(homeForRole("customer")).toBe("/dashboard");
    expect(homeForRole("platform_admin")).toBe("/admin/dashboard");
    expect(homeForRole("operations")).toBe("/admin/dashboard");
    expect(homeForRole("support")).toBe("/admin/support");
    expect(homeForRole("employer_admin")).toBe("/employer");
    expect(homeForRole("programme_partner")).toBe("/partner/impact");
  });

  it("keeps staff, employer and programme-partner areas role-scoped", () => {
    expect(canRoleOpenPath("support", "/admin/support")).toBe(true);
    expect(canRoleOpenPath("employer_admin", "/admin/dashboard")).toBe(false);
    expect(canRoleOpenPath("employer_admin", "/employer")).toBe(true);
    expect(canRoleOpenPath("programme_partner", "/partner/impact")).toBe(true);
    expect(canRoleOpenPath("programme_partner", "/dashboard")).toBe(false);
  });

  it("limits support users to the operations modules assigned to their role", () => {
    expect(canRoleOpenPath("support", "/admin/dashboard")).toBe(true);
    expect(canRoleOpenPath("support", "/admin/ledger")).toBe(true);
    expect(canRoleOpenPath("support", "/admin/audit-trail")).toBe(true);
    expect(canRoleOpenPath("support", "/admin/compliance")).toBe(false);
    expect(canRoleOpenPath("support", "/admin/autopilot")).toBe(false);
    expect(canRoleOpenPath("support", "/admin/platform-governance")).toBe(false);
  });

  it("allows operations users into governed operations modules", () => {
    expect(canRoleOpenPath("operations", "/admin/compliance")).toBe(true);
    expect(canRoleOpenPath("operations", "/admin/platform-governance")).toBe(true);
    expect(canRoleOpenPath("operations", "/admin/inclusion/delivery")).toBe(true);
  });

  it("keeps participant check-ins and Financial Spaces in the personal experience", () => {
    expect(canRoleOpenPath("customer", "/programme/check-ins")).toBe(true);
    expect(canRoleOpenPath("programme_partner", "/programme/check-ins")).toBe(false);
    expect(canRoleOpenPath("customer", "/spaces")).toBe(true);
  });

  it("recognises portal routes that require a session", () => {
    expect(isProtectedPortalPath("/financial-passport")).toBe(true);
    expect(isProtectedPortalPath("/spaces/42")).toBe(true);
    expect(isProtectedPortalPath("/")).toBe(false);
    expect(isProtectedPortalPath("/account/delete")).toBe(false);
  });
});
