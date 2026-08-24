import Link from "next/link";
import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { capabilityLabel } from "@/lib/capabilities";
import { formatUgx } from "@/lib/format";

export default async function DashboardPage() {
  const token = await getAccessToken();

  try {
    const profile = await opfinApi.profile(token);
    const user = profile.data.user;
    const [kycResult, consentResult, balanceResult, capabilityResult] = await Promise.allSettled([
      opfinApi.kycStatus(token),
      opfinApi.consents(token),
      opfinApi.loanBalance(user.id, token),
      opfinApi.capabilities("UG", token)
    ]);

    const kyc = kycResult.status === "fulfilled" ? kycResult.value.data.latest_case : null;
    const consents = consentResult.status === "fulfilled" ? consentResult.value.data.consents : [];
    const activeCreditConsent = consents.find((consent) => consent.purpose === "credit_processing" && consent.status === "granted");
    const outstanding = balanceResult.status === "fulfilled" ? balanceResult.value.data.outstandingAmount : null;
    const capabilities = capabilityResult.status === "fulfilled" ? capabilityResult.value.data.capabilities : {};
    const paymentCapability = capabilities.payments;

    const nextAction = !kyc || kyc.status !== "verified"
      ? { title: "Complete verification", text: "Verify your identity so OpFin can safely unlock regulated financial products.", href: "/kyc", action: "Continue verification" }
      : !activeCreditConsent
        ? { title: "Review your permissions", text: "Grant only the permissions needed before OpFin uses your information for credit assessment.", href: "/consent", action: "Review permissions" }
        : outstanding && outstanding > 0
          ? { title: "Stay ahead of repayment", text: `You currently have ${formatUgx(outstanding)} outstanding. Review your loan before the next payment is due.`, href: "/loans/account", action: "View loan" }
          : { title: "Build your financial position", text: "Start with a goal or check what you may be eligible to borrow. OpFin will keep product details inside the journey.", href: "/save", action: "Set a goal" };

    return (
      <Screen
        title={`Welcome, ${user.name}`}
        description="Your Financial Compass shows what is known today, what needs attention, and the clearest next step."
      >
        <section className="panel compass-next-action">
          <p className="eyebrow">Recommended next step</p>
          <h2>{nextAction.title}</h2>
          <p className="muted">{nextAction.text}</p>
          <Link className="button compass-action" href={nextAction.href}>{nextAction.action}</Link>
        </section>

        <div className="grid grid-3 compass-grid">
          <section className="panel">
            <h2>Identity</h2>
            <div className="stat stat-text">{kyc?.status ?? user.nin_status ?? "Not verified"}</div>
            <p className="muted">Your verification status controls access to regulated products.</p>
          </section>
          <section className="panel">
            <h2>Debt obligations</h2>
            <div className="stat">{outstanding === null ? "Unavailable" : formatUgx(outstanding)}</div>
            <p className="muted">Only confirmed OpFin loan obligations are included in this first view.</p>
          </section>
          <section className="panel">
            <h2>Payments</h2>
            <div className="stat stat-text">{capabilityLabel(paymentCapability)}</div>
            <p className="muted">Money movement is routed through CPay when the capability is enabled for this environment.</p>
          </section>
        </div>

        <section className="panel">
          <h2>Quick actions</h2>
          <div className="quick-actions" aria-label="Financial actions">
            <Link className="button" href="/borrow">Borrow</Link>
            <Link className="button secondary" href="/save">Save</Link>
            <Link className="button secondary" href="/support">Get help</Link>
            <Link className="button secondary" href="/more">More</Link>
          </div>
        </section>

        <div className="grid grid-2">
          <section className="panel">
            <h2>Your money picture</h2>
            <p className="muted">
              Budgeting, linked-account cash flow, financial calendar and safe-to-spend calculations are being added as governed capabilities. OpFin will not invent an available balance from incomplete data.
            </p>
          </section>
          <section className="panel">
            <h2>Data permissions</h2>
            <div className="stat stat-text">{activeCreditConsent ? "Credit consent active" : "Review needed"}</div>
            <p className="muted">You can review and revoke purpose-specific permissions from More.</p>
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load your Financial Compass.";

    return (
      <Screen title="Home" description="Your Financial Compass will appear here when your account data is available.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
