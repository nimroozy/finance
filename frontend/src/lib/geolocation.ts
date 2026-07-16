export type GeoPermission = "granted" | "denied" | "unavailable";

export interface GeoResult {
  lat: number;
  lng: number;
  accuracy: number | null;
  permission: GeoPermission;
}

export function getCurrentPosition(
  options?: PositionOptions,
): Promise<GeoResult> {
  return new Promise((resolve) => {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      resolve({
        lat: 0,
        lng: 0,
        accuracy: null,
        permission: "unavailable",
      });
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        resolve({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          accuracy: pos.coords.accuracy ?? null,
          permission: "granted",
        });
      },
      (err) => {
        const permission: GeoPermission =
          err.code === err.PERMISSION_DENIED ? "denied" : "unavailable";
        resolve({
          lat: 0,
          lng: 0,
          accuracy: null,
          permission,
        });
      },
      {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
        ...options,
      },
    );
  });
}

export function mapsExternalUrl(lat: number, lng: number) {
  return `https://www.google.com/maps?q=${lat},${lng}`;
}

export function waMeUrl(phone: string, text?: string) {
  const digits = phone.replace(/\D/g, "");
  const base = `https://wa.me/${digits}`;
  return text ? `${base}?text=${encodeURIComponent(text)}` : base;
}

export function telUrl(phone: string) {
  return `tel:${phone.replace(/\s/g, "")}`;
}
