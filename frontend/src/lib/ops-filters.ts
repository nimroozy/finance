"use client";

import { useCallback, useMemo } from "react";
import { usePathname, useRouter } from "@/i18n/navigation";
import { useSearchParams } from "next/navigation";

/** Read/write URL search params for server-side list filters. */
export function useUrlFilters<T extends Record<string, string>>(
  defaults: T,
) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const values = useMemo(() => {
    const next = { ...defaults };
    for (const key of Object.keys(defaults) as Array<keyof T>) {
      const raw = searchParams.get(String(key));
      if (raw != null && raw !== "") {
        next[key] = raw as T[keyof T];
      }
    }
    return next;
  }, [defaults, searchParams]);

  const setFilters = useCallback(
    (patch: Partial<T>, options?: { resetPage?: boolean }) => {
      const params = new URLSearchParams(searchParams.toString());
      for (const [key, value] of Object.entries(patch)) {
        if (value === undefined || value === null || value === "" || value === defaults[key as keyof T]) {
          params.delete(key);
        } else {
          params.set(key, String(value));
        }
      }
      if (options?.resetPage !== false && !("page" in patch)) {
        params.delete("page");
      }
      const qs = params.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname);
    },
    [defaults, pathname, router, searchParams],
  );

  const clearFilters = useCallback(() => {
    router.replace(pathname);
  }, [pathname, router]);

  return { values, setFilters, clearFilters, searchParams };
}

export function todayIsoDate() {
  return new Date().toISOString().slice(0, 10);
}

export function formatAge(
  value: string | null | undefined,
  locale = "en",
): string {
  if (!value) return "—";
  const ms = Date.now() - new Date(value).getTime();
  if (Number.isNaN(ms) || ms < 0) return "—";
  const hours = Math.floor(ms / 3_600_000);
  const days = Math.floor(hours / 24);
  if (locale === "fa") {
    if (days >= 1) return `${days} روز`;
    if (hours >= 1) return `${hours} ساعت`;
    const mins = Math.max(1, Math.floor(ms / 60_000));
    return `${mins} دقیقه`;
  }
  if (days >= 1) return `${days}d`;
  if (hours >= 1) return `${hours}h`;
  const mins = Math.max(1, Math.floor(ms / 60_000));
  return `${mins}m`;
}

export function slaLabelFromState(
  sla?: {
    breached_at?: string | null;
    paused_at?: string | null;
    response_state?: string | null;
    resolution_state?: string | null;
    response_remaining_seconds?: number | null;
    resolution_remaining_seconds?: number | null;
  } | null,
): string {
  if (!sla) return "—";
  if (sla.breached_at || sla.response_state === "breached" || sla.resolution_state === "breached") {
    return "breached";
  }
  if (sla.paused_at || sla.response_state === "paused" || sla.resolution_state === "paused") {
    return "paused";
  }
  const responseRisk =
    typeof sla.response_remaining_seconds === "number" &&
    sla.response_remaining_seconds > 0 &&
    sla.response_remaining_seconds <= 1800;
  const resolutionRisk =
    typeof sla.resolution_remaining_seconds === "number" &&
    sla.resolution_remaining_seconds > 0 &&
    sla.resolution_remaining_seconds <= 3600;
  if (responseRisk || resolutionRisk) return "at_risk";
  return "ok";
}
