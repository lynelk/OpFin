import { Screen, StateNotice } from "@/components/Screen";
import { locationContextsApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function LocationInsightsPage() {
  const token = await getAccessToken();

  try {
    const response = await locationContextsApi.insights(token);
    const rows = response.data.rows;

    return (
      <Screen
        title="Location insights"
        description="Aggregate geographic context for group coverage and partner service networks. Individual customer locations are not exposed here."
      >
        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">Privacy boundary</p>
              <h2>Coverage, not surveillance</h2>
            </div>
            <span className="badge">minimum cohort {response.data.minimum_cohort}</span>
          </div>
          <p className="muted">
            This view reports only Financial Space and partner service-point
            geography. It does not expose an individual customer pin or use
            location as a credit score input.
          </p>
        </section>

        <section className="panel">
          <h2>Geographic coverage</h2>
          {rows.length ? (
            <div style={{ overflowX: "auto" }}>
              <table className="table">
                <thead>
                  <tr>
                    <th>Country</th>
                    <th>Region</th>
                    <th>District / area</th>
                    <th>Context</th>
                    <th>Purpose</th>
                    <th>Count</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row, index) => (
                    <tr
                      key={[
                        row.country_code,
                        row.admin_area_1,
                        row.admin_area_2,
                        row.subject_type,
                        row.purpose,
                        index
                      ].join("-")}
                    >
                      <td>{row.country_code ?? "—"}</td>
                      <td>{row.admin_area_1 ?? "—"}</td>
                      <td>{row.admin_area_2 ?? "—"}</td>
                      <td>{row.subject_type.replaceAll("_", " ")}</td>
                      <td>{row.purpose.replaceAll("_", " ")}</td>
                      <td>{row.count}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <StateNotice
              state="empty"
              message="No geographic cohort currently meets the minimum privacy threshold."
            />
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen
        title="Location insights"
        description="Aggregate geographic coverage for authorised operations."
      >
        <StateNotice
          state="server"
          message={
            error instanceof Error ? error.message : "Unable to load location insights."
          }
        />
      </Screen>
    );
  }
}
