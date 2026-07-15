import { clsx, type ClassValue } from "@/lib/clsx";

export function cn(...inputs: ClassValue[]) {
  return clsx(inputs);
}

export type { ClassValue };

export function formatDateTime(
  value: string | null | undefined,
  locale = "en",
): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat(locale === "fa" ? "fa-IR" : "en-GB", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

export function formatDate(
  value: string | null | undefined,
  locale = "en",
): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat(locale === "fa" ? "fa-IR" : "en-GB", {
      dateStyle: "medium",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

export function formatMoney(
  value: string | number | null | undefined,
  currency?: string | null,
  locale = "en",
): string {
  if (value === null || value === undefined || value === "") return "—";
  const amount = typeof value === "number" ? value : Number(value);
  if (Number.isNaN(amount)) return String(value);
  try {
    return new Intl.NumberFormat(locale === "fa" ? "fa-IR" : "en-US", {
      style: currency ? "currency" : "decimal",
      currency: currency || undefined,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return amount.toLocaleString(locale === "fa" ? "fa-IR" : "en-US");
  }
}
