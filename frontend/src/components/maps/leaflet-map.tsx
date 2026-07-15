"use client";

import { useEffect, useMemo } from "react";
import {
  MapContainer,
  TileLayer,
  Marker,
  Popup,
  useMap,
} from "react-leaflet";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import { mapsExternalUrl } from "@/lib/geolocation";

export type MapMarker = {
  id: string | number;
  lat: number;
  lng: number;
  label?: string;
};

function FitBounds({ markers }: { markers: MapMarker[] }) {
  const map = useMap();
  useEffect(() => {
    if (markers.length === 0) return;
    if (markers.length === 1) {
      map.setView([markers[0].lat, markers[0].lng], 14);
      return;
    }
    const bounds = L.latLngBounds(
      markers.map((m) => [m.lat, m.lng] as [number, number]),
    );
    map.fitBounds(bounds, { padding: [40, 40] });
  }, [map, markers]);
  return null;
}

const defaultIcon = L.icon({
  iconUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png",
  iconRetinaUrl:
    "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png",
  shadowUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png",
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

export function LeafletMapInner({
  markers,
  height = 280,
  missingLabel = "Location not available",
  openInMapsLabel = "Open in maps",
}: {
  markers: MapMarker[];
  height?: number;
  missingLabel?: string;
  openInMapsLabel?: string;
}) {
  const valid = useMemo(
    () =>
      markers.filter(
        (m) =>
          Number.isFinite(m.lat) &&
          Number.isFinite(m.lng) &&
          !(m.lat === 0 && m.lng === 0),
      ),
    [markers],
  );

  if (valid.length === 0) {
    return (
      <div
        className="flex items-center justify-center rounded-lg border border-border bg-sand-soft/40 text-sm text-muted"
        style={{ height }}
      >
        {missingLabel}
      </div>
    );
  }

  const center: [number, number] = [valid[0].lat, valid[0].lng];

  return (
    <div className="overflow-hidden rounded-lg border border-border" style={{ height }}>
      <MapContainer
        center={center}
        zoom={13}
        style={{ height: "100%", width: "100%" }}
        scrollWheelZoom={false}
      >
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        <FitBounds markers={valid} />
        {valid.map((m) => (
          <Marker key={m.id} position={[m.lat, m.lng]} icon={defaultIcon}>
            <Popup>
              <div className="space-y-1 text-sm">
                {m.label ? <p className="font-medium">{m.label}</p> : null}
                <a
                  href={mapsExternalUrl(m.lat, m.lng)}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-primary underline"
                >
                  {openInMapsLabel}
                </a>
              </div>
            </Popup>
          </Marker>
        ))}
      </MapContainer>
    </div>
  );
}
