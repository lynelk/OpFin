import { randomUUID } from "node:crypto";
import Link from "next/link";
import {
  confirmTreasuryReconciliationAction,
  createTreasuryAccountAction,
  generateConsolidatedStatementAction,
  generateTreasuryStatementAction,
  importTreasuryStatementAction,
  reconcileTreasuryStatementAction,
  recordTreasuryTransactionAction,
  resolveReconciliationTodoAction
} from "@/app/financial-space-statement-actions";
import { Screen, StateNotice } from "@/components/Screen";
import { financialSpacesApi } from "@/lib/api/client";
import {
  financialSpaceStatementsApi,
  type StatementImport,
  type TreasuryAccount
} from "@/lib/api/financial-space-statements";
import { getAccessToken } from "@/lib/auth/session";

const managerRoles = new Set([
  "owner",
  "administrator",
  "admin",
  "chairperson",
  "treasurer",
  "secretary",
  "director",
  "manager"
]);

function money(value: unknown, currency = "UGX") {
  const amount = typeof value === "number" ? value : Number(value ?? 0);
  return currency + " " + new Intl.NumberFormat("en-UG").format(
    Number.isFinite(amount) ? amount : 0
  );
}

function monthRange() {
  const now = new Date();
  const from = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), 1));
  const to = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 0));
  return {
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10)
  };
}

export default async function TreasuryPage({
  params,
  searchParams
}: {
  params: Promise<{ id: string }>;
  searchParams?: Promise<{
    account?: string;
    import?: string;
    statement?: string;
    status?: string;
    error?: string;
    message?: string;
  }>;
}) {
  const { id } = await params;
  const query = await searchParams;
  const token = await getAccessToken();
  const spaceId = Number(id);
  const defaults = monthRange();
  const cashbookIdempotencyKey = randomUUID();

  try {
    const [spacesResponse, accountsResponse] = await Promise.all([
      financialSpacesApi.list(token),
      financialSpaceStatementsApi.accounts(spaceId, token)
    ]);

    const space = spacesResponse.data.spaces.find((item) => item.id === spaceId);
    if (!space) throw new Error("Financial Space not found.");

    const accounts = accountsResponse.accounts;
    const requestedAccountId = Number(query?.account ?? 0);
    const selectedAccount =
      accounts.find((account) => account.id === requestedAccountId) ??
      accounts[0] ??
      null;
    const canManage = managerRoles.has(space.role);

    let transactions: Awaited<
      ReturnType<typeof financialSpaceStatementsApi.transactions>
    >["transactions"] = [];
    let imports: StatementImport[] = [];
    let statements: Awaited<
      ReturnType<typeof financialSpaceStatementsApi.statements>
    >["statements"] = [];
    let consolidatedStatements: Awaited<
      ReturnType<typeof financialSpaceStatementsApi.statements>
    >["statements"] = [];
    let selectedImport: Record<string, unknown> | null = null;
    let selectedStatement:
      | Awaited<ReturnType<typeof financialSpaceStatementsApi.statement>>
      | null = null;

    if (selectedAccount) {
      const [txResponse, importResponse, statementResponse, allStatementResponse] = await Promise.all([
        financialSpaceStatementsApi.transactions(spaceId, selectedAccount.id, token),
        financialSpaceStatementsApi.imports(spaceId, selectedAccount.id, token),
        financialSpaceStatementsApi.statements(spaceId, selectedAccount.id, token),
        financialSpaceStatementsApi.statements(spaceId, undefined, token)
      ]);
      transactions = txResponse.transactions;
      imports = importResponse.imports;
      statements = statementResponse.statements.filter(
        (statement) => statement.statement_scope !== "consolidated"
      );
      consolidatedStatements = allStatementResponse.statements.filter(
        (statement) => statement.statement_scope === "consolidated"
      );

      const importId = Number(query?.import ?? 0);
      if (importId) {
        selectedImport = (
          await financialSpaceStatementsApi.importDetail(spaceId, importId, token)
        ).import as unknown as Record<string, unknown>;
      }

      const statementId = Number(query?.statement ?? 0);
      if (statementId) {
        selectedStatement = await financialSpaceStatementsApi.statement(
          spaceId,
          statementId,
          token
        );
      }
    }

    const successMessage: Record<string, string> = {
      "account-created": "Treasury account created.",
      "transaction-recorded": "Treasury transaction recorded.",
      "statement-imported": "External statement imported.",
      reconciled: "Statement reconciliation completed.",
      "statement-generated": "Bank-style statement issued.",
      "consolidated-statement-generated": "Consolidated all-activity statement issued.",
      "row-matched": "Statement exception matched to the selected OpFin transaction.",
      "todo-resolved": "Reconciliation to-do resolved.",
      "reconciliation-confirmed": "Reconciliation confirmed."
    };

    return (
      <Screen
        title="Treasury & statements"
        description={
          space.name +
          " · cashbook, external statement reconciliation and member-ready statements."
        }
      >
        {query?.status ? (
          <StateNotice
            state="success"
            message={successMessage[query.status] ?? "Treasury update completed."}
          />
        ) : null}
        {query?.message ? (
          <StateNotice
            state={query.error === "validation" ? "validation" : "server"}
            message={query.message}
          />
        ) : null}

        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">Financial control</p>
              <h2>Club treasury</h2>
            </div>
            <span className="badge">
              {canManage ? "finance role" : "member view"}
            </span>
          </div>
          <p className="muted">
            OpFin keeps the club cashbook, imported bank/mobile-money/custodian
            evidence and issued statements separate but reconcilable. Imported
            evidence never silently rewrites the internal book.
          </p>
        </section>

        {accounts.length ? (
          <section className="panel">
            <h2>Choose account</h2>
            <form method="get" className="inline-form">
              <select name="account" defaultValue={String(selectedAccount?.id ?? "")}>
                {accounts.map((account) => (
                  <option key={account.id} value={account.id}>
                    {account.account_name} · {account.currency} ·{" "}
                    {money(account.current_balance_minor, account.currency)}
                  </option>
                ))}
              </select>
              <button className="button secondary" type="submit">
                View
              </button>
            </form>
          </section>
        ) : (
          <StateNotice
            state="empty"
            message={
              canManage
                ? "No treasury account exists yet. Create the club's first bank, mobile-money, cash or custodian account below."
                : "No treasury account has been configured for this club yet."
            }
          />
        )}

        {canManage && accounts.length ? (
          <section className="panel">
            <div className="case-card-head">
              <div>
                <p className="eyebrow">All activity</p>
                <h2>Consolidated OpFin statement</h2>
              </div>
              <span className="badge">all treasury accounts</span>
            </div>
            <p className="muted">
              Combines all treasury-account activity for the period and adds the
              recorded Financial Space asset/obligation position. Currencies stay
              separate unless an explicit FX valuation policy is introduced.
            </p>
            <form action={generateConsolidatedStatementAction} className="form-grid">
              <input type="hidden" name="space_id" value={spaceId} />
              <div className="field">
                <label htmlFor="consolidated_from">From</label>
                <input
                  id="consolidated_from"
                  name="from"
                  type="date"
                  defaultValue={defaults.from}
                  required
                />
              </div>
              <div className="field">
                <label htmlFor="consolidated_to">To</label>
                <input
                  id="consolidated_to"
                  name="to"
                  type="date"
                  defaultValue={defaults.to}
                  required
                />
              </div>
              <button className="button" type="submit">
                Issue consolidated statement
              </button>
            </form>
            {consolidatedStatements.length ? (
              <div style={{ marginTop: 16 }}>
                <h3>Recent consolidated statements</h3>
                <div className="inline-form">
                  {consolidatedStatements.slice(0, 6).map((statement) => (
                    <a
                      key={statement.id}
                      className="button secondary"
                      href={"/api/space-statements/" + spaceId + "/" + statement.id + "/html"}
                      target="_blank"
                      rel="noreferrer"
                    >
                      {statement.statement_number}
                    </a>
                  ))}
                </div>
              </div>
            ) : null}
          </section>
        ) : null}

        {selectedAccount ? (
          <>
            <div className="grid grid-3">
              <section className="panel">
                <p className="eyebrow">Account</p>
                <h2>{selectedAccount.account_name}</h2>
                <p className="muted">
                  {selectedAccount.institution_name ?? selectedAccount.account_type}
                  {selectedAccount.account_reference_masked
                    ? " · " + selectedAccount.account_reference_masked
                    : ""}
                </p>
              </section>
              <section className="panel">
                <p className="eyebrow">Book balance</p>
                <div className="stat">
                  {money(
                    selectedAccount.current_balance_minor,
                    selectedAccount.currency
                  )}
                </div>
              </section>
              <section className="panel">
                <p className="eyebrow">External evidence</p>
                <div className="stat">{imports.length}</div>
                <p className="muted">recent statement imports</p>
              </section>
            </div>

            <section className="panel">
              <h2>Recent cashbook transactions</h2>
              {transactions.length ? (
                <div style={{ overflowX: "auto" }}>
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Details</th>
                        <th>Reference</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Reconciliation</th>
                      </tr>
                    </thead>
                    <tbody>
                      {transactions.slice(0, 25).map((transaction) => (
                        <tr key={transaction.id}>
                          <td>{transaction.transaction_date}</td>
                          <td>{transaction.description}</td>
                          <td>{transaction.transaction_reference ?? "—"}</td>
                          <td>
                            {transaction.direction === "debit"
                              ? money(
                                  transaction.amount_minor,
                                  transaction.currency
                                )
                              : "—"}
                          </td>
                          <td>
                            {transaction.direction === "credit"
                              ? money(
                                  transaction.amount_minor,
                                  transaction.currency
                                )
                              : "—"}
                          </td>
                          <td>
                            <span className="badge">
                              {transaction.reconciliation_status.replaceAll(
                                "_",
                                " "
                              )}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <StateNotice
                  state="empty"
                  message="No treasury transactions have been recorded for this account."
                />
              )}
            </section>

            <section className="panel">
              <h2>Imported statements & reconciliation</h2>
              {imports.length ? (
                <div className="grid grid-2">
                  {imports.map((item) => (
                    <article className="case-card" key={item.id}>
                      <p className="eyebrow">
                        {item.original_filename ?? "Imported statement"}
                      </p>
                      <h3>
                        {item.period_start ?? "Unknown start"} →{" "}
                        {item.period_end ?? "Unknown end"}
                      </h3>
                      <p className="muted">
                        {item.row_count} rows · {item.matched_count} matched ·{" "}
                        {item.exception_count} exceptions
                      </p>
                      <div className="inline-form">
                        <Link
                          className="button secondary"
                          href={
                            "/spaces/" +
                            spaceId +
                            "/treasury?account=" +
                            selectedAccount.id +
                            "&import=" +
                            item.id
                          }
                        >
                          Inspect
                        </Link>
                        {canManage && item.status !== "reconciled" ? (
                          <form action={reconcileTreasuryStatementAction}>
                            <input type="hidden" name="space_id" value={spaceId} />
                            <input
                              type="hidden"
                              name="account_id"
                              value={selectedAccount.id}
                            />
                            <input type="hidden" name="import_id" value={item.id} />
                            <button className="button" type="submit">
                              Reconcile
                            </button>
                          </form>
                        ) : null}
                      </div>
                    </article>
                  ))}
                </div>
              ) : (
                <StateNotice
                  state="empty"
                  message="No bank, mobile-money or custodian statement has been imported yet."
                />
              )}

              {selectedImport ? (
                <div style={{ marginTop: 20 }}>
                  <h3>Selected import</h3>
                  <p className="muted">
                    Status: {String(selectedImport.status ?? "unknown")} · Rows:{" "}
                    {String(selectedImport.row_count ?? 0)} · Exceptions:{" "}
                    {String(selectedImport.exception_count ?? 0)}
                  </p>
                  {Array.isArray(selectedImport.review_todos) &&
                  selectedImport.review_todos.length ? (
                    <section className="case-card" style={{ marginTop: 12 }}>
                      <p className="eyebrow">Reconciliation review</p>
                      <h3>{selectedImport.review_todos.length} to-do(s) before confirmation</h3>
                      <p className="muted">
                        OpFin has already auto-matched the high-confidence items.
                        Review only the remaining decisions.
                      </p>
                      <div className="grid grid-2">
                        {(selectedImport.review_todos as Array<Record<string, unknown>>).map(
                          (todo, index) => {
                            const type = String(todo.type ?? "");
                            const suggestions = Array.isArray(todo.suggested_matches)
                              ? (todo.suggested_matches as Array<Record<string, unknown>>)
                              : [];
                            return (
                              <article className="case-card" key={type + "-" + index}>
                                <p className="eyebrow">
                                  {type.replaceAll("_", " ")}
                                </p>
                                <h3>{String(todo.description ?? "Review reconciliation item")}</h3>
                                {todo.amount_minor != null ? (
                                  <p>
                                    {money(
                                      todo.amount_minor,
                                      selectedAccount.currency
                                    )}{" "}
                                    · {String(todo.direction ?? "")}
                                  </p>
                                ) : null}

                                {type === "statement_row" && suggestions.length ? (
                                  <div>
                                    <p className="muted">Suggested matches</p>
                                    {suggestions.map((suggestion) => (
                                      <form
                                        key={String(suggestion.transaction_id)}
                                        action={resolveReconciliationTodoAction}
                                        className="inline-form"
                                      >
                                        <input type="hidden" name="space_id" value={spaceId} />
                                        <input
                                          type="hidden"
                                          name="account_id"
                                          value={selectedAccount.id}
                                        />
                                        <input
                                          type="hidden"
                                          name="import_id"
                                          value={String(selectedImport.id)}
                                        />
                                        <input type="hidden" name="todo_type" value="statement_row" />
                                        <input
                                          type="hidden"
                                          name="row_id"
                                          value={String(todo.row_id)}
                                        />
                                        <input
                                          type="hidden"
                                          name="action"
                                          value="match_transaction"
                                        />
                                        <input
                                          type="hidden"
                                          name="transaction_id"
                                          value={String(suggestion.transaction_id)}
                                        />
                                        <button className="button secondary" type="submit">
                                          Match {String(suggestion.reference ?? suggestion.transaction_id)} ·{" "}
                                          {String(suggestion.confidence_percent)}%
                                        </button>
                                      </form>
                                    ))}
                                  </div>
                                ) : null}

                                {type === "statement_row" ? (
                                  <>
                                    <form
                                      action={resolveReconciliationTodoAction}
                                      className="form-grid"
                                    >
                                      <input type="hidden" name="space_id" value={spaceId} />
                                      <input
                                        type="hidden"
                                        name="account_id"
                                        value={selectedAccount.id}
                                      />
                                      <input
                                        type="hidden"
                                        name="import_id"
                                        value={String(selectedImport.id)}
                                      />
                                      <input type="hidden" name="todo_type" value="statement_row" />
                                      <input
                                        type="hidden"
                                        name="row_id"
                                        value={String(todo.row_id)}
                                      />
                                      <input type="hidden" name="action" value="create_book_entry" />
                                      <div className="field">
                                        <label htmlFor={"create-reason-" + index}>Note (optional)</label>
                                        <input
                                          id={"create-reason-" + index}
                                          name="reason"
                                          placeholder="Why should this statement item be added to the cashbook?"
                                        />
                                      </div>
                                      <button className="button secondary" type="submit">
                                        Create missing book entry
                                      </button>
                                    </form>

                                    <form
                                      action={resolveReconciliationTodoAction}
                                      className="form-grid"
                                    >
                                      <input type="hidden" name="space_id" value={spaceId} />
                                      <input
                                        type="hidden"
                                        name="account_id"
                                        value={selectedAccount.id}
                                      />
                                      <input
                                        type="hidden"
                                        name="import_id"
                                        value={String(selectedImport.id)}
                                      />
                                      <input type="hidden" name="todo_type" value="statement_row" />
                                      <input
                                        type="hidden"
                                        name="row_id"
                                        value={String(todo.row_id)}
                                      />
                                      <div className="field">
                                        <label htmlFor={"accept-reason-" + index}>
                                          Reason for accepted exception
                                        </label>
                                        <input
                                          id={"accept-reason-" + index}
                                          name="reason"
                                          required
                                        />
                                      </div>
                                      <div className="inline-form">
                                        <button
                                          className="button secondary"
                                          type="submit"
                                          name="action"
                                          value="mark_external_only"
                                        >
                                          External-only item
                                        </button>
                                        <button
                                          className="button secondary"
                                          type="submit"
                                          name="action"
                                          value="mark_duplicate"
                                        >
                                          Mark duplicate
                                        </button>
                                      </div>
                                    </form>
                                  </>
                                ) : null}

                                {type === "book_transaction" ? (
                                  <form
                                    action={resolveReconciliationTodoAction}
                                    className="form-grid"
                                  >
                                    <input type="hidden" name="space_id" value={spaceId} />
                                    <input
                                      type="hidden"
                                      name="account_id"
                                      value={selectedAccount.id}
                                    />
                                    <input
                                      type="hidden"
                                      name="import_id"
                                      value={String(selectedImport.id)}
                                    />
                                    <input type="hidden" name="todo_type" value="book_transaction" />
                                    <input
                                      type="hidden"
                                      name="transaction_id"
                                      value={String(todo.transaction_id)}
                                    />
                                    <div className="field">
                                      <label htmlFor={"book-reason-" + index}>
                                        Why is this book-only?
                                      </label>
                                      <input id={"book-reason-" + index} name="reason" required />
                                    </div>
                                    <button className="button secondary" type="submit">
                                      Accept book-only item
                                    </button>
                                  </form>
                                ) : null}

                                {type === "balance_variance" ? (
                                  <form
                                    action={resolveReconciliationTodoAction}
                                    className="form-grid"
                                  >
                                    <input type="hidden" name="space_id" value={spaceId} />
                                    <input
                                      type="hidden"
                                      name="account_id"
                                      value={selectedAccount.id}
                                    />
                                    <input
                                      type="hidden"
                                      name="import_id"
                                      value={String(selectedImport.id)}
                                    />
                                    <input type="hidden" name="todo_type" value="balance_variance" />
                                    <div className="field">
                                      <label htmlFor={"variance-reason-" + index}>
                                        Reason for accepting variance
                                      </label>
                                      <p className="muted">
                                        {Number(todo.opening_balance_variance_minor ?? 0) !== 0
                                          ? "Opening variance: " +
                                            money(
                                              todo.opening_balance_variance_minor,
                                              selectedAccount.currency
                                            )
                                          : ""}
                                        {Number(todo.opening_balance_variance_minor ?? 0) !== 0 &&
                                        Number(todo.closing_balance_variance_minor ?? 0) !== 0
                                          ? " · "
                                          : ""}
                                        {Number(todo.closing_balance_variance_minor ?? 0) !== 0
                                          ? "Closing variance: " +
                                            money(
                                              todo.closing_balance_variance_minor,
                                              selectedAccount.currency
                                            )
                                          : ""}
                                      </p>
                                      <input id={"variance-reason-" + index} name="reason" required />
                                    </div>
                                    <button className="button secondary" type="submit">
                                      Accept variance with reason
                                    </button>
                                  </form>
                                ) : null}
                              </article>
                            );
                          }
                        )}
                      </div>
                    </section>
                  ) : canManage &&
                    String(selectedImport.confirmation_status ?? "") === "ready" ? (
                    <section className="case-card" style={{ marginTop: 12 }}>
                      <p className="eyebrow">Ready to close</p>
                      <h3>All reconciliation to-dos are cleared</h3>
                      <p className="muted">
                        If any exception or balance variance was accepted, confirmation must be completed by a different authorised finance officer.
                      </p>
                      <form action={confirmTreasuryReconciliationAction} className="form-grid">
                        <input type="hidden" name="space_id" value={spaceId} />
                        <input
                          type="hidden"
                          name="account_id"
                          value={selectedAccount.id}
                        />
                        <input
                          type="hidden"
                          name="import_id"
                          value={String(selectedImport.id)}
                        />
                        <div className="field">
                          <label htmlFor="confirmation-note">Confirmation note (optional)</label>
                          <input
                            id="confirmation-note"
                            name="note"
                            placeholder="e.g. Reviewed against September bank statement"
                          />
                        </div>
                        <button className="button" type="submit">
                          Confirm reconciliation
                        </button>
                      </form>
                    </section>
                  ) : null}

                  {Array.isArray(selectedImport.rows) &&
                  selectedImport.rows.length ? (
                    <div style={{ overflowX: "auto" }}>
                      <table className="table">
                        <thead>
                          <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Exception</th>
                          </tr>
                        </thead>
                        <tbody>
                          {(selectedImport.rows as Array<Record<string, unknown>>)
                            .slice(0, 50)
                            .map((row) => (
                              <tr key={String(row.id)}>
                                <td>{String(row.transaction_date ?? "")}</td>
                                <td>{String(row.description ?? "")}</td>
                                <td>
                                  {String(row.statement_reference ?? "—")}
                                </td>
                                <td>
                                  {money(
                                    row.amount_minor,
                                    String(row.currency ?? selectedAccount.currency)
                                  )}
                                </td>
                                <td>
                                  {String(
                                    row.reconciliation_status ?? "unmatched"
                                  ).replaceAll("_", " ")}
                                </td>
                                <td>
                                  {String(row.exception_type ?? "—").replaceAll(
                                    "_",
                                    " "
                                  )}
                                </td>
                              </tr>
                            ))}
                        </tbody>
                      </table>
                    </div>
                  ) : null}
                </div>
              ) : null}
            </section>

            <section className="panel">
              <h2>Issue a bank-style statement</h2>
              <p className="muted">
                Statements are immutable snapshots. If the books are corrected
                later, issue a new statement rather than changing the old one.
              </p>
              <form action={generateTreasuryStatementAction} className="form-grid">
                <input type="hidden" name="space_id" value={spaceId} />
                <input
                  type="hidden"
                  name="account_id"
                  value={selectedAccount.id}
                />
                <div className="field">
                  <label htmlFor="from">From</label>
                  <input
                    id="from"
                    name="from"
                    type="date"
                    defaultValue={defaults.from}
                    required
                  />
                </div>
                <div className="field">
                  <label htmlFor="to">To</label>
                  <input
                    id="to"
                    name="to"
                    type="date"
                    defaultValue={defaults.to}
                    required
                  />
                </div>
                <button className="button" type="submit">
                  Issue statement
                </button>
              </form>
            </section>

            <section className="panel">
              <h2>Issued statements</h2>
              {statements.length ? (
                <div style={{ overflowX: "auto" }}>
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Statement</th>
                        <th>Period</th>
                        <th>Closing balance</th>
                        <th>Reconciliation</th>
                        <th>Files</th>
                      </tr>
                    </thead>
                    <tbody>
                      {statements.map((statement) => (
                        <tr key={statement.id}>
                          <td>
                            <Link
                              href={
                                "/spaces/" +
                                spaceId +
                                "/treasury?account=" +
                                selectedAccount.id +
                                "&statement=" +
                                statement.id
                              }
                            >
                              {statement.statement_number}
                            </Link>
                          </td>
                          <td>
                            {statement.period_start} → {statement.period_end}
                          </td>
                          <td>
                            {money(
                              statement.closing_balance_minor,
                              selectedAccount.currency
                            )}
                          </td>
                          <td>
                            {statement.reconciliation_status.replaceAll("_", " ")}
                          </td>
                          <td>
                            <div className="inline-form">
                              <a
                                className="button secondary"
                                href={
                                  "/api/space-statements/" +
                                  spaceId +
                                  "/" +
                                  statement.id +
                                  "/html"
                                }
                                target="_blank"
                                rel="noreferrer"
                              >
                                Open
                              </a>
                              <a
                                className="button secondary"
                                href={
                                  "/api/space-statements/" +
                                  spaceId +
                                  "/" +
                                  statement.id +
                                  "/csv"
                                }
                              >
                                CSV
                              </a>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <StateNotice
                  state="empty"
                  message="No statement has been issued from this account yet."
                />
              )}

              {selectedStatement ? (
                <div style={{ marginTop: 20 }}>
                  <h3>{selectedStatement.statement.statement_number}</h3>
                  {selectedStatement.statement.statement_scope === "consolidated" ? (
                    <>
                      <p className="muted">
                        Consolidated all-activity statement · currencies reported separately.
                      </p>
                      <div className="grid grid-3">
                        {Object.entries(selectedStatement.totals_by_currency).map(
                          ([currency, totals]) => (
                            <div className="case-card" key={currency}>
                              <p className="eyebrow">{currency}</p>
                              <strong>
                                Closing {money(totals.closing_balance_minor, currency)}
                              </strong>
                              <p className="muted">
                                Debits {money(totals.total_debits_minor, currency)} · Credits{" "}
                                {money(totals.total_credits_minor, currency)}
                              </p>
                            </div>
                          )
                        )}
                        <div className="case-card">
                          <p className="eyebrow">Integrity</p>
                          <strong>
                            {selectedStatement.statement.content_hash.slice(0, 16)}…
                          </strong>
                        </div>
                      </div>
                    </>
                  ) : (
                    <div className="grid grid-3">
                      <div className="case-card">
                        <p className="eyebrow">Opening</p>
                        <strong>
                          {money(
                            selectedStatement.statement.opening_balance_minor,
                            selectedStatement.account?.currency ?? space.currency
                          )}
                        </strong>
                      </div>
                      <div className="case-card">
                        <p className="eyebrow">Closing</p>
                        <strong>
                          {money(
                            selectedStatement.statement.closing_balance_minor,
                            selectedStatement.account?.currency ?? space.currency
                          )}
                        </strong>
                      </div>
                      <div className="case-card">
                        <p className="eyebrow">Integrity</p>
                        <strong>
                          {selectedStatement.statement.content_hash.slice(0, 16)}…
                        </strong>
                      </div>
                    </div>
                  )}
                </div>
              ) : null}
            </section>
          </>
        ) : null}

        {canManage ? (
          <div className="grid grid-2">
            <section className="panel">
              <h2>Add treasury account</h2>
              <form action={createTreasuryAccountAction} className="form-grid">
                <input type="hidden" name="space_id" value={spaceId} />
                <div className="field">
                  <label htmlFor="account_name">Account name</label>
                  <input id="account_name" name="account_name" required />
                </div>
                <div className="field">
                  <label htmlFor="account_type">Type</label>
                  <select id="account_type" name="account_type" defaultValue="bank">
                    <option value="bank">Bank</option>
                    <option value="mobile_money">Mobile money</option>
                    <option value="cash">Cash</option>
                    <option value="custodian">Custodian</option>
                    <option value="broker">Broker</option>
                    <option value="investment_wallet">Investment wallet</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="institution_name">Institution</label>
                  <input id="institution_name" name="institution_name" />
                </div>
                <div className="field">
                  <label htmlFor="account_reference">Account reference</label>
                  <input id="account_reference" name="account_reference" />
                </div>
                <div className="field">
                  <label htmlFor="currency">Currency</label>
                  <input id="currency" name="currency" defaultValue={space.currency} />
                </div>
                <div className="field">
                  <label htmlFor="opening_balance_minor">Opening balance</label>
                  <input
                    id="opening_balance_minor"
                    name="opening_balance_minor"
                    type="number"
                    defaultValue="0"
                  />
                </div>
                <div className="field">
                  <label htmlFor="balance_as_of">Opening balance as of</label>
                  <input id="balance_as_of" name="balance_as_of" type="date" />
                  <p className="muted">
                    Required when the opening balance is not zero. This date remains the cashbook baseline.
                  </p>
                </div>
                <button className="button" type="submit">
                  Create account
                </button>
              </form>
            </section>

            {selectedAccount ? (
              <section className="panel">
                <h2>Record cashbook transaction</h2>
                <form
                  action={recordTreasuryTransactionAction}
                  className="form-grid"
                >
                  <input type="hidden" name="space_id" value={spaceId} />
                  <input
                    type="hidden"
                    name="account_id"
                    value={selectedAccount.id}
                  />
                  <input
                    type="hidden"
                    name="idempotency_key"
                    value={cashbookIdempotencyKey}
                  />
                  <div className="field">
                    <label htmlFor="direction">Direction</label>
                    <select id="direction" name="direction" defaultValue="credit">
                      <option value="credit">Credit / money in</option>
                      <option value="debit">Debit / money out</option>
                    </select>
                  </div>
                  <div className="field">
                    <label htmlFor="amount_minor">Amount</label>
                    <input
                      id="amount_minor"
                      name="amount_minor"
                      type="number"
                      min="1"
                      required
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="transaction_type">Type</label>
                    <input
                      id="transaction_type"
                      name="transaction_type"
                      placeholder="member_contribution"
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="transaction_reference">Reference</label>
                    <input
                      id="transaction_reference"
                      name="transaction_reference"
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="description">Description</label>
                    <input id="description" name="description" required />
                  </div>
                  <div className="field">
                    <label htmlFor="counterparty_name">Counterparty</label>
                    <input id="counterparty_name" name="counterparty_name" />
                  </div>
                  <div className="field">
                    <label htmlFor="transaction_date">Transaction date</label>
                    <input
                      id="transaction_date"
                      name="transaction_date"
                      type="date"
                      required
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="value_date">Value date</label>
                    <input id="value_date" name="value_date" type="date" />
                  </div>
                  <button className="button" type="submit">
                    Record transaction
                  </button>
                </form>
              </section>
            ) : null}

            {selectedAccount ? (
              <section className="panel">
                <h2>Import external statement</h2>
                <p className="muted">
                  CSV is supported in this release. Map the column headings used by
                  the bank, mobile-money provider, broker or custodian.
                </p>
                <form
                  action={importTreasuryStatementAction}
                  className="form-grid"
                >
                  <input type="hidden" name="space_id" value={spaceId} />
                  <input
                    type="hidden"
                    name="account_id"
                    value={selectedAccount.id}
                  />
                  <div className="field">
                    <label htmlFor="statement_file">CSV statement</label>
                    <input
                      id="statement_file"
                      name="statement_file"
                      type="file"
                      accept=".csv,text/csv,text/plain"
                      required
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="minor_unit_exponent">
                      Decimal places in imported amounts
                    </label>
                    <input
                      id="minor_unit_exponent"
                      name="minor_unit_exponent"
                      type="number"
                      min="0"
                      max="4"
                      defaultValue="0"
                    />
                  </div>
                  {[
                    ["date_column", "Date column", "Date"],
                    ["value_date_column", "Value date column", "Value Date"],
                    ["description_column", "Description column", "Description"],
                    ["reference_column", "Reference column", "Reference"],
                    ["debit_column", "Debit column", "Debit"],
                    ["credit_column", "Credit column", "Credit"],
                    ["balance_column", "Balance column", "Balance"]
                  ].map(([name, label, defaultValue]) => (
                    <div className="field" key={name}>
                      <label htmlFor={name}>{label}</label>
                      <input id={name} name={name} defaultValue={defaultValue} />
                    </div>
                  ))}
                  <details>
                    <summary>Single amount + direction format</summary>
                    <div className="form-grid">
                      <div className="field">
                        <label htmlFor="amount_column">Amount column</label>
                        <input id="amount_column" name="amount_column" />
                      </div>
                      <div className="field">
                        <label htmlFor="direction_column">Direction column</label>
                        <input id="direction_column" name="direction_column" />
                      </div>
                    </div>
                  </details>
                  <div className="field">
                    <label htmlFor="opening_balance_minor">
                      Statement opening balance
                    </label>
                    <input
                      id="opening_balance_minor"
                      name="opening_balance_minor"
                      type="number"
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="closing_balance_minor">
                      Statement closing balance
                    </label>
                    <input
                      id="closing_balance_minor"
                      name="closing_balance_minor"
                      type="number"
                    />
                  </div>
                  <button className="button" type="submit">
                    Import statement
                  </button>
                </form>
              </section>
            ) : null}
          </div>
        ) : null}

        <section className="panel">
          <Link className="button secondary" href={"/spaces/" + spaceId}>
            Back to Financial Space
          </Link>
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen
        title="Treasury & statements"
        description="Investment Club and group treasury controls."
      >
        <StateNotice
          state="server"
          message={
            error instanceof Error ? error.message : "Unable to load treasury."
          }
        />
      </Screen>
    );
  }
}
