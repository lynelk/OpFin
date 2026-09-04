import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { createCustomerSupportCaseAction } from "./actions";

export default async function SupportPage({
  searchParams
}: Readonly<{
  searchParams?: Promise<{ status?: string; error?: string; message?: string }>;
}>) {
  const token = await getAccessToken();
  const params = searchParams ? await searchParams : {};

  try {
    const response = await opfinApi.customerSupportCases(token);
    const cases = response.data.support_cases;

    return (
      <Screen
        title="Support"
        description="Create a traceable case and follow what is happening, who owns it, and what happens next."
      >
        {params.status === "created" ? <StateNotice state="success" message="Your support case was created." /> : null}
        {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Unable to create support case."} /> : null}

        <div className="grid grid-2">
          <section className="panel">
            <h2>Get help</h2>
            <form action={createCustomerSupportCaseAction} className="form-grid">
              <div className="field">
                <label htmlFor="category">What do you need help with?</label>
                <select id="category" name="category" required defaultValue="payment">
                  <option value="payment">Payment or transfer</option>
                  <option value="repayment">Loan repayment</option>
                  <option value="kyc">Identity or KYC</option>
                  <option value="credit">Borrowing decision</option>
                  <option value="crb_dispute">CRB dispute</option>
                  <option value="hardship">Repayment difficulty</option>
                  <option value="fraud">Suspicious activity</option>
                  <option value="general">General help</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="subject">Subject</label>
                <input id="subject" name="subject" required maxLength={160} placeholder="Short description of the issue" />
              </div>
              <div className="field">
                <label htmlFor="description">Tell us what happened</label>
                <textarea id="description" name="description" required maxLength={4000} rows={5} placeholder="Include what you expected and what happened instead." />
              </div>
              <div className="grid grid-2">
                <div className="field">
                  <label htmlFor="related_type">Related to</label>
                  <select id="related_type" name="related_type" defaultValue="">
                    <option value="">Not sure</option>
                    <option value="payment">Payment</option>
                    <option value="loan">Loan</option>
                    <option value="application">Application</option>
                    <option value="kyc">KYC case</option>
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="related_reference">Reference, if known</label>
                  <input id="related_reference" name="related_reference" maxLength={160} placeholder="Optional" />
                </div>
              </div>
              <button className="button" type="submit">Create support case</button>
            </form>
          </section>

          <section className="panel">
            <h2>Your cases</h2>
            {cases.length === 0 ? (
              <p className="muted">You do not have any support cases yet.</p>
            ) : (
              <div className="case-list">
                {cases.map((supportCase) => (
                  <article className="case-card" key={supportCase.id}>
                    <div className="case-card-head">
                      <strong>{supportCase.subject}</strong>
                      <span className="badge">{supportCase.status.replaceAll("_", " ")}</span>
                    </div>
                    <p className="muted">{supportCase.case_number} · {supportCase.category}</p>
                    {supportCase.notes?.[0] ? <p>{supportCase.notes[0].note}</p> : null}
                  </article>
                ))}
              </div>
            )}
          </section>
        </div>
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load support cases.";

    return (
      <Screen title="Support" description="Your support cases will appear here when the service is available.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
