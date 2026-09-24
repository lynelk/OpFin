import { Buffer } from "node:buffer";
import Image from "next/image";
import { Screen, StateNotice } from "@/components/Screen";
import { financialSpacesApi, locationContextsApi } from "@/lib/api/client";
import { getAccessToken } from "@/lib/auth/session";
import { formatUgx } from "@/lib/format";


async function staticMapDataUrl(
  contextId: number,
  token: string | undefined,
  zoom = 14
): Promise<string | null> {
  const apiBase = process.env.NEXT_PUBLIC_OPFIN_API_URL;
  if (!apiBase) return null;

  try {
    const response = await fetch(
      apiBase + "/location/static-map/" + contextId + "?zoom=" + zoom,
      {
        headers: token ? { Authorization: "Bearer " + token } : {},
        cache: "no-store"
      }
    );
    if (!response.ok) return null;
    const type = response.headers.get("content-type") ?? "image/png";
    const bytes = Buffer.from(await response.arrayBuffer());
    return "data:" + type + ";base64," + bytes.toString("base64");
  } catch {
    return null;
  }
}

export default async function SpacePage({
  params
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  const token = await getAccessToken();
  const spaceId = Number(id);

  try {
    const [spaces, life, workspace, locationsResponse, locationStatus] =
      await Promise.all([
        financialSpacesApi.list(token),
        financialSpacesApi.financialLife(spaceId, token),
        financialSpacesApi.workspace(spaceId, token).catch(() => null),
        locationContextsApi.list("financial_space", spaceId, token),
        locationContextsApi.status(token).catch(() => null)
      ]);

    const space = spaces.data.spaces.find((item) => item.id === spaceId);
    if (!space) throw new Error("Space not found.");

    const locations = locationsResponse.data.locations;
    const operatingArea = locations.find(
      (item) => item.purpose === "group_operating_area"
    );
    const meetingPlace = locations.find(
      (item) => item.purpose === "group_meeting_place"
    );
    const previewLocation = meetingPlace ?? operatingArea;
    const mapImage =
      previewLocation &&
      locationStatus?.data.static_maps_enabled &&
      previewLocation.latitude != null &&
      previewLocation.longitude != null
        ? await staticMapDataUrl(previewLocation.id, token)
        : null;

    return (
      <Screen
        title={space.name}
        description="Your authorised view of this Financial Space."
      >
        <div className="grid grid-3">
          <section className="panel">
            <h2>Net worth / position</h2>
            <div className="stat">{formatUgx(life.data.net_worth_minor)}</div>
          </section>
          <section className="panel">
            <h2>Safe to spend</h2>
            <div className="stat">{formatUgx(life.data.safe_to_spend_minor)}</div>
          </section>
          <section className="panel">
            <h2>Debt</h2>
            <div className="stat">{formatUgx(life.data.debt_minor)}</div>
          </section>
        </div>

        <div className="grid grid-3">
          <section className="panel">
            <h2>Assets</h2>
            <div className="stat">{formatUgx(life.data.assets_minor)}</div>
          </section>
          <section className="panel">
            <h2>Owed to this space</h2>
            <div className="stat">{formatUgx(life.data.receivables_minor)}</div>
          </section>
          <section className="panel">
            <h2>Upcoming 30 days</h2>
            <div className="stat">{formatUgx(life.data.upcoming_30d_minor)}</div>
          </section>
        </div>

        <section className="panel">
          <div className="case-card-head">
            <div>
              <p className="eyebrow">Location context</p>
              <h2>Where this Space operates</h2>
            </div>
            <span className="badge">
              background tracking: off
            </span>
          </div>

          {previewLocation ? (
            <div className="grid grid-2">
              <div>
                <p>
                  <strong>
                    {previewLocation.place_name ??
                      previewLocation.locality ??
                      "Recorded location"}
                  </strong>
                </p>
                <p className="muted">
                  {previewLocation.formatted_address ??
                    [
                      previewLocation.admin_area_2,
                      previewLocation.admin_area_1,
                      previewLocation.country_code
                    ]
                      .filter(Boolean)
                      .join(", ")}
                </p>
                <p className="muted">
                  Precision: {previewLocation.precision_level.replaceAll("_", " ")}
                  {" · "}
                  Source: {previewLocation.source.replaceAll("_", " ")}
                </p>
                {meetingPlace && operatingArea ? (
                  <p className="muted">
                    This Space has both a member meeting place and a broader operating
                    area recorded.
                  </p>
                ) : null}
                {previewLocation.maps_url ? (
                  <a
                    className="button secondary"
                    href={previewLocation.maps_url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Open in Google Maps
                  </a>
                ) : null}
              </div>

              {mapImage ? (
                <div>
                  <Image
                    src={mapImage}
                    alt={"Map preview for " + (previewLocation.place_name ?? space.name)}
                    width={640}
                    height={320}
                    unoptimized
                    style={{
                      width: "100%",
                      height: 240,
                      objectFit: "cover",
                      borderRadius: 14
                    }}
                  />
                </div>
              ) : (
                <StateNotice
                  state="empty"
                  message="A lightweight static map preview will appear here when Google Maps is activated and this location has coordinates."
                />
              )}
            </div>
          ) : (
            <StateNotice
              state="empty"
              message="No operating area or meeting place is recorded yet. Members can continue using the Space without sharing location."
            />
          )}
        </section>

        {workspace ? (
          <section className="panel">
            <h2>Workspace</h2>
            <p className="muted">
              Institutional controls, capabilities and onboarding status are
              available according to your role. The organisation does not inherit
              access to your Personal Space.
            </p>
          </section>
        ) : null}
      </Screen>
    );
  } catch (error) {
    return (
      <Screen
        title="Financial space"
        description="Your authorised financial context."
      >
        <StateNotice
          state="server"
          message={
            error instanceof Error ? error.message : "Unable to load this space."
          }
        />
      </Screen>
    );
  }
}
