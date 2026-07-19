"use client";

import { AlertTriangle, Lock, RefreshCw, WifiOff, Ban } from "lucide-react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type ErrorStateProps = {
  error: unknown;
  onRetry?: () => void;
  className?: string;
};

/**
 * Renders the right message + action for any failed request: permission
 * (403), conflict (409), validation (422), offline/network, or a generic
 * failure — never a silent blank page.
 */
export function ErrorState({ error, onRetry, className }: ErrorStateProps) {
  const t = useTranslations("errors");
  const tCommon = useTranslations("common");

  const status = error instanceof ApiError ? error.status : null;
  const isNetwork =
    !(error instanceof ApiError) &&
    error instanceof TypeError &&
    /fetch|network/i.test(error.message);

  let icon = AlertTriangle;
  let title = t("genericTitle");
  let description = t("genericBody");

  if (isNetwork || status === 0) {
    icon = WifiOff;
    title = t("connectionTitle");
    description = t("connectionBody");
  } else if (status === 401 || status === 403) {
    icon = Lock;
    title = t("permissionTitle");
    description = t("permissionBody");
  } else if (status === 409) {
    icon = Ban;
    title = t("conflictTitle");
    description = t("conflictBody");
  } else if (status === 422) {
    icon = AlertTriangle;
    title = t("validationTitle");
    description = t("validationBody");
  } else if (status === 404) {
    icon = AlertTriangle;
    title = t("notFoundTitle");
    description = t("notFoundBody");
  } else if (error instanceof ApiError && error.message) {
    description = error.message;
  }

  const Icon = icon;
  const fieldErrors =
    error instanceof ApiError && error.errors ? Object.entries(error.errors) : [];

  return (
    <div
      role="alert"
      className={cn(
        "flex flex-col items-center gap-3 rounded-lg border border-danger/20 bg-danger-soft/40 px-4 py-8 text-center",
        className,
      )}
    >
      <Icon className="h-8 w-8 text-danger" aria-hidden />
      <div className="space-y-1">
        <p className="text-sm font-semibold text-foreground">{title}</p>
        <p className="max-w-sm text-sm text-muted">{description}</p>
      </div>
      {fieldErrors.length > 0 ? (
        <ul className="mt-1 space-y-1 text-start text-sm text-danger">
          {fieldErrors.map(([field, messages]) => (
            <li key={field}>
              <span className="font-medium">{field}:</span> {messages.join(", ")}
            </li>
          ))}
        </ul>
      ) : null}
      {onRetry ? (
        <Button variant="secondary" size="sm" onClick={onRetry} className="mt-1">
          <RefreshCw className="h-3.5 w-3.5" aria-hidden />
          {tCommon("retry")}
        </Button>
      ) : null}
    </div>
  );
}

/** Dedicated network/offline failure panel (no response reached the server). */
export function ConnectionError({ onRetry, className }: { onRetry?: () => void; className?: string }) {
  const t = useTranslations("errors");
  const tCommon = useTranslations("common");
  return (
    <div
      role="alert"
      className={cn(
        "flex flex-col items-center gap-3 rounded-lg border border-border bg-surface-muted px-4 py-8 text-center",
        className,
      )}
    >
      <WifiOff className="h-8 w-8 text-muted" aria-hidden />
      <div className="space-y-1">
        <p className="text-sm font-semibold text-foreground">{t("connectionTitle")}</p>
        <p className="max-w-sm text-sm text-muted">{t("connectionBody")}</p>
      </div>
      {onRetry ? (
        <Button variant="secondary" size="sm" onClick={onRetry}>
          <RefreshCw className="h-3.5 w-3.5" aria-hidden />
          {tCommon("retry")}
        </Button>
      ) : null}
    </div>
  );
}

/** 403 — user is authenticated but lacks permission for this resource. */
export function PermissionDenied({ className }: { className?: string }) {
  const t = useTranslations("errors");
  return (
    <div
      role="alert"
      className={cn(
        "flex flex-col items-center gap-3 rounded-lg border border-border bg-surface-muted px-4 py-8 text-center",
        className,
      )}
    >
      <Lock className="h-8 w-8 text-muted" aria-hidden />
      <div className="space-y-1">
        <p className="text-sm font-semibold text-foreground">{t("permissionTitle")}</p>
        <p className="max-w-sm text-sm text-muted">{t("permissionBody")}</p>
      </div>
    </div>
  );
}

/** Generic inline retry affordance for smaller, non-full-page failures. */
export function RetryPanel({
  message,
  onRetry,
  className,
}: {
  message?: string;
  onRetry: () => void;
  className?: string;
}) {
  const t = useTranslations("errors");
  const tCommon = useTranslations("common");
  return (
    <div
      role="alert"
      className={cn(
        "flex items-center justify-between gap-3 rounded-md border border-danger/20 bg-danger-soft/40 px-3 py-2 text-sm",
        className,
      )}
    >
      <span className="text-danger">{message ?? t("genericBody")}</span>
      <Button variant="secondary" size="sm" onClick={onRetry}>
        <RefreshCw className="h-3.5 w-3.5" aria-hidden />
        {tCommon("retry")}
      </Button>
    </div>
  );
}
