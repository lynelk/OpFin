import { revalidatePath } from "next/cache";
import { Screen, StateNotice } from "@/components/Screen";
import { experienceApi } from "@/lib/api/experience";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

async function createProgram(formData: FormData) {
  "use server";
  const token = await getAccessToken();
  await experienceApi.createEmployerProgram({
    name: String(formData.get("name")),
    benefit_type: String(formData.get("benefit_type")),
    eligibility_rules: { employment_status: "active" },
    configuration: { pilot: true }
  }, token);
  revalidatePath("/employer");
}

export default async function EmployerPortalPage() {
  const token = await getAccessToken();
  try {
    const workspace = await experienceApi.employer(token);
    if (!workspace.employer || !workspace.membership) {
      return (
        <Screen title="OpFin Work" description="Employer-linked financial wellbeing and benefit access.">
          <StateNotice state="empty" message="No active employer relationship is linked to this account. Employer access appears only after a verified membership is created." />
        </Screen>
      );
    }

    const canManage = ["admin", "hr"].includes(workspace.membership.membership_role);
    return (
      <Screen title="OpFin Work" description="Permission-led employer verification and benefit administration without turning HR into a lending desk.">
        <section className="panel compass-next-action">
          <p className="eyebrow">VERIFIED EMPLOYER RELATIONSHIP</p>
          <div className="case-card-head"><div><h2>{workspace.employer.name}</h2><p className="muted">Membership: {workspace.membership.membership_role} · {workspace.membership.employment_status}</p></div><span className="badge ok">{workspace.employer.status}</span></div>
        </section>

        <div className="grid grid-3">
          <section className="panel"><h2>Verified income</h2><div className="stat">{workspace.membership.verified_monthly_income_minor ? formatUgx(workspace.membership.verified_monthly_income_minor) : "Not verified"}</div><p className="muted">Employer evidence is distinct from customer estimates.</p></section>
          <section className="panel"><h2>Benefit programs</h2><div className="stat">{workspace.programs.length}</div><p className="muted">Programs follow explicit eligibility rules.</p></section>
          <section className="panel"><h2>Employment type</h2><div className="stat stat-text">{workspace.membership.employment_type ?? "Not recorded"}</div><p className="muted">Used only where relevant and permitted.</p></section>
        </div>

        <section className="panel">
          <h2>Available programs</h2>
          {workspace.programs.length === 0 ? <p className="muted">No active employer benefit programs.</p> : <div className="experience-card-grid">{workspace.programs.map((program) => <article className="experience-card" key={program.id}><div className="case-card-head"><strong>{program.name}</strong><span className="badge">{program.status}</span></div><p className="muted">{program.benefit_type.replaceAll("_", " ")}</p></article>)}</div>}
        </section>

        {canManage ? (
          <>
            <section className="panel">
              <h2>Create a draft benefit program</h2>
              <p className="muted">Creation does not activate financial benefits. Approval and provider capability remain separate controls.</p>
              <form action={createProgram} className="experience-form-row" style={{ marginTop: 16 }}>
                <div className="field"><label htmlFor="program-name">Program name</label><input id="program-name" name="name" required placeholder="Employee financial wellbeing" /></div>
                <div className="field"><label htmlFor="benefit-type">Benefit type</label><select id="benefit-type" name="benefit_type"><option value="financial_wellbeing">Financial wellbeing</option><option value="salary_linked_credit">Salary-linked credit</option><option value="savings_support">Savings support</option><option value="protection">Protection</option></select></div>
                <button className="button" type="submit">Create draft</button>
              </form>
            </section>

            <section className="panel">
              <h2>Employees</h2>
              {workspace.employees.length === 0 ? <p className="muted">No employee memberships available.</p> : <div style={{ overflowX: "auto" }}><table className="table"><thead><tr><th>Name</th><th>Reference</th><th>Status</th><th>Type</th><th>Verified income</th></tr></thead><tbody>{workspace.employees.map((employee) => <tr key={employee.id}><td>{employee.name}</td><td>{employee.employee_reference ?? "—"}</td><td>{employee.employment_status}</td><td>{employee.employment_type ?? "—"}</td><td>{employee.verified_monthly_income_minor ? formatUgx(employee.verified_monthly_income_minor) : "Not verified"}</td></tr>)}</tbody></table></div>}
            </section>
          </>
        ) : null}
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    return <Screen title="OpFin Work" description="Employer-linked financial wellbeing and benefits."><StateNotice state={state} message={error instanceof Error ? error.message : "Unable to load employer workspace."} /></Screen>;
  }
}
