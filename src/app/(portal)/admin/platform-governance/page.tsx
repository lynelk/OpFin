import { Screen, StateNotice } from "@/components/Screen";
import {
  approveDecisionRuleAction,
  approveHardshipAction,
  approveWorkflowDefinitionAction,
  createDecisionRuleAction,
  createProductDefinitionAction,
  createWorkflowDefinitionAction,
  transitionProductDefinitionAction
} from "@/app/v5-p0-actions";

export default async function PlatformGovernancePage({ searchParams }: Readonly<{ searchParams?: Promise<{ status?: string; error?: string; message?: string }> }>) {
  const params = searchParams ? await searchParams : {};
  return (
    <Screen title="Platform Governance" description="Operate product definitions, decision rules, workflows and hardship approvals through maker-checker controlled APIs.">
      {params.status ? <StateNotice state="success" message={`Governance action completed: ${params.status.replaceAll("-", " ")}.`} /> : null}
      {params.error ? <StateNotice state={params.error as "validation" | "unauthorized" | "forbidden" | "server" | "network"} message={params.message ?? "Governance action failed."} /> : null}

      <section className="panel">
        <h2>Hardship approval</h2>
        <p className="muted">Approval is rejected when the approver is the same actor who created the request.</p>
        <form action={approveHardshipAction} className="form-grid">
          <div className="grid grid-2">
            <div className="field"><label htmlFor="case_id">Case ID</label><input id="case_id" name="case_id" type="number" min="1" required /></div>
            <div className="field"><label htmlFor="approved_relief">Approved relief JSON</label><textarea id="approved_relief" name="approved_relief" rows={3} defaultValue="[]" required /></div>
          </div>
          <button className="button" type="submit">Approve hardship case</button>
        </form>
      </section>

      <section className="panel">
        <h2>Product Factory</h2>
        <form action={createProductDefinitionAction} className="form-grid">
          <div className="grid grid-2">
            <div className="field"><label htmlFor="product_code">Product code</label><input id="product_code" name="product_code" required /></div>
            <div className="field"><label htmlFor="product_name">Product name</label><input id="product_name" name="name" required /></div>
          </div>
          <div className="field"><label htmlFor="definition">Versioned product definition JSON</label><textarea id="definition" name="definition" rows={6} defaultValue="{}" required /></div>
          <button className="button" type="submit">Create draft product</button>
        </form>
        <form action={transitionProductDefinitionAction} className="form-grid">
          <div className="grid grid-2">
            <div className="field"><label htmlFor="product_id">Product ID</label><input id="product_id" name="product_id" type="number" min="1" required /></div>
            <div className="field"><label htmlFor="product_status">Next lifecycle state</label><select id="product_status" name="status" defaultValue="submitted"><option value="submitted">Submitted</option><option value="approved">Approved</option><option value="active">Active</option><option value="retired">Retired</option></select></div>
          </div>
          <button className="button secondary" type="submit">Transition product</button>
        </form>
      </section>

      <section className="panel">
        <h2>Rules Engine</h2>
        <form action={createDecisionRuleAction} className="form-grid">
          <div className="grid grid-3">
            <div className="field"><label htmlFor="rule_code">Rule code</label><input id="rule_code" name="rule_code" required /></div>
            <div className="field"><label htmlFor="rule_name">Rule name</label><input id="rule_name" name="name" required /></div>
            <div className="field"><label htmlFor="priority">Priority</label><input id="priority" name="priority" type="number" defaultValue="100" /></div>
          </div>
          <div className="grid grid-2">
            <div className="field"><label htmlFor="conditions">Conditions JSON</label><textarea id="conditions" name="conditions" rows={6} defaultValue="[]" required /></div>
            <div className="field"><label htmlFor="rule_actions">Actions JSON</label><textarea id="rule_actions" name="actions" rows={6} defaultValue="[]" required /></div>
          </div>
          <button className="button" type="submit">Create draft rule</button>
        </form>
        <form action={approveDecisionRuleAction} className="form-grid">
          <div className="field"><label htmlFor="rule_id">Rule ID for independent approval</label><input id="rule_id" name="rule_id" type="number" min="1" required /></div>
          <button className="button secondary" type="submit">Approve rule</button>
        </form>
      </section>

      <section className="panel">
        <h2>Workflow Engine</h2>
        <form action={createWorkflowDefinitionAction} className="form-grid">
          <div className="grid grid-2">
            <div className="field"><label htmlFor="workflow_code">Workflow code</label><input id="workflow_code" name="workflow_code" required /></div>
            <div className="field"><label htmlFor="workflow_name">Workflow name</label><input id="workflow_name" name="name" required /></div>
          </div>
          <div className="grid grid-2">
            <div className="field"><label htmlFor="states">States JSON</label><textarea id="states" name="states" rows={6} defaultValue="[]" required /></div>
            <div className="field"><label htmlFor="transitions">Transitions JSON</label><textarea id="transitions" name="transitions" rows={6} defaultValue="[]" required /></div>
          </div>
          <button className="button" type="submit">Create draft workflow</button>
        </form>
        <form action={approveWorkflowDefinitionAction} className="form-grid">
          <div className="field"><label htmlFor="workflow_id">Workflow ID for independent approval</label><input id="workflow_id" name="workflow_id" type="number" min="1" required /></div>
          <button className="button secondary" type="submit">Approve workflow</button>
        </form>
      </section>
    </Screen>
  );
}
