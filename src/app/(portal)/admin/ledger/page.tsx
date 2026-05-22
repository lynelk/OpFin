import { DataTable } from "@/components/DataTable";
import { Screen, StateNotice } from "@/components/Screen";
import { opfinApi } from "@/lib/api/client";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";

export default async function AdminLedgerPage() {
  const token = await getAccessToken();

  try {
    const response = await opfinApi.ledgerTransactions(token);
    const transactions = response.data.ledger_transactions;

    return (
      <Screen title="Ledger" description="Immutable production ledger postings for loan and payment operations.">
        {transactions.length === 0 ? (
          <StateNotice state="empty" message="No production ledger transactions are available." />
        ) : (
          <DataTable
            rows={transactions}
            getKey={(row) => row.id}
            columns={[
              { label: "Reference", render: (row) => row.reference },
              { label: "Event", render: (row) => <span className="badge">{row.event_type}</span> },
              { label: "Posted", render: (row) => new Date(row.posted_at).toLocaleString("en-UG") },
              {
                label: "Debits",
                render: (row) =>
                  formatUgx(
                    row.entries
                      .filter((entry) => entry.direction === "debit")
                      .reduce((total, entry) => total + entry.amount_minor, 0)
                  )
              },
              {
                label: "Credits",
                render: (row) =>
                  formatUgx(
                    row.entries
                      .filter((entry) => entry.direction === "credit")
                      .reduce((total, entry) => total + entry.amount_minor, 0)
                  )
              },
              {
                label: "Entries",
                render: (row) => (
                  <div className="stack-list">
                    {row.entries.map((entry) => (
                      <span key={entry.id}>
                        {entry.direction}: {formatUgx(entry.amount_minor)}
                      </span>
                    ))}
                  </div>
                )
              }
            ]}
          />
        )}
      </Screen>
    );
  } catch (error) {
    const state = error instanceof OpfinApiError ? error.kind : "server";
    const message = error instanceof Error ? error.message : "Unable to load production ledger.";

    return (
      <Screen title="Ledger" description="Immutable production ledger postings for loan and payment operations.">
        <StateNotice state={state} message={message} />
      </Screen>
    );
  }
}
