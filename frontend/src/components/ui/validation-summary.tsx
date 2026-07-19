"use client";

import { useTranslations } from "next-intl";
import { AlertTriangle } from "lucide-react";
import { ApiError } from "@/lib/api";

/** Lists every field error from a 422 ApiError at the top of a form. */
export function ValidationSummary({ error }: { error: unknown }) {
  const t = useTranslations("errors");
  if (!(error instanceof ApiError) || !error.errors || Object.keys(error.errors).length === 0) {
    if (error instanceof ApiError && error.status === 422) {
      return (
        <div role="alert" className="rounded-md border border-danger/30 bg-danger-soft px-3 py-2 text-sm text-danger">
          {error.message || t("validationBody")}
        </div>
      );
    }
    return null;
  }

  return (
    <div role="alert" className="rounded-md border border-danger/30 bg-danger-soft px-3 py-2 text-sm text-danger">
      <p className="flex items-center gap-1.5 font-medium">
        <AlertTriangle className="h-4 w-4" aria-hidden />
        {t("validationTitle")}
      </p>
      <ul className="mt-1.5 list-inside list-disc space-y-0.5">
        {Object.entries(error.errors).map(([field, messages]) => (
          <li key={field}>{messages.join(", ")}</li>
        ))}
      </ul>
    </div>
  );
}
