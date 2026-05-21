import { StateNotice } from "@/components/Screen";

export default function PortalLoading() {
  return (
    <div className="content">
      <StateNotice state="loading" message="Loading OpFin demo data..." />
    </div>
  );
}
