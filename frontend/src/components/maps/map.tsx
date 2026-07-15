"use client";

import dynamic from "next/dynamic";
import type { MapMarker } from "@/components/maps/leaflet-map";

const LeafletMapInner = dynamic(
  () =>
    import("@/components/maps/leaflet-map").then((m) => m.LeafletMapInner),
  {
    ssr: false,
    loading: () => (
      <div className="flex h-[280px] items-center justify-center rounded-lg border border-border text-sm text-muted">
        Loading map…
      </div>
    ),
  },
);

export function LeafletMap(props: {
  markers: MapMarker[];
  height?: number;
  missingLabel?: string;
  openInMapsLabel?: string;
}) {
  return <LeafletMapInner {...props} />;
}

export type { MapMarker };
