import { Screen, StateNotice } from "@/components/Screen";
import { locationContextsApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";

export default async function PartnerLocationPage() {
  const token = await getAccessToken();

  try {
    const response = await locationContextsApi.partnerNetwork(token);
    const points = response.data.service_points;

    return (
      <Screen
        title="Service network"
        description="Your organisation's recorded service points and coverage locations."
      >
        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">Location boundary</p>
              <h2>Your network only</h2>
            </div>
            <span className="badge">customer pins hidden</span>
          </div>
          <p className="muted">
            This view contains your organisation's own service points. It does not
            expose individual customer or member home locations.
          </p>
        </section>

        <section className="panel">
          <h2>Recorded service points</h2>
          {points.length ? (
            <div className="grid grid-2">
              {points.map((point) => (
                <article className="case-card" key={point.id}>
                  <p className="eyebrow">
                    {point.partner_name ?? "Partner service point"}
                  </p>
                  <h3>
                    {point.place_name ??
                      point.locality ??
                      point.formatted_address ??
                      "Recorded location"}
                  </h3>
                  <p className="muted">
                    {point.formatted_address ??
                      [point.admin_area_2, point.admin_area_1, point.country_code]
                        .filter(Boolean)
                        .join(", ")}
                  </p>
                  <p className="muted">
                    {point.verification_status.replaceAll("_", " ")}
                  </p>
                  {point.maps_url ? (
                    <a
                      className="button secondary"
                      href={point.maps_url}
                      target="_blank"
                      rel="noreferrer"
                    >
                      Open map
                    </a>
                  ) : null}
                </article>
              ))}
            </div>
          ) : (
            <StateNotice
              state="empty"
              message="No service points are currently recorded for your organisation."
            />
          )}
        </section>
      </Screen>
    );
  } catch (error) {
    return (
      <Screen
        title="Service network"
        description="Your organisation's recorded service points."
      >
        <StateNotice
          state="server"
          message={
            error instanceof Error
              ? error.message
              : "Unable to load partner service points."
          }
        />
      </Screen>
    );
  }
}
