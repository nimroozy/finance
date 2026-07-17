"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Button } from "@/components/ui/button";
import { EmptyState, Panel } from "@/components/ui/layout";
import { cn } from "@/lib/utils";

export function EmptyWorkspace({
  label,
  action,
  className,
}: {
  label?: string;
  action?: React.ReactNode;
  className?: string;
}) {
  const t = useTranslations("opsUi");
  return (
    <Panel className={cn("p-8", className)}>
      <EmptyState label={label ?? t("emptyWorkspace")} />
      {action ? <div className="mt-4 flex justify-center">{action}</div> : null}
    </Panel>
  );
}

export function ErrorWorkspace({
  message,
  onRetry,
  title,
  className,
}: {
  message?: string;
  onRetry?: () => void;
  title?: string;
  className?: string;
}) {
  const t = useTranslations("opsUi");
  const tCommon = useTranslations("common");
  const tShell = useTranslations("shell");
  return (
    <div role="alert" data-testid="error-workspace">
      <Panel className={cn("space-y-4 p-8 text-center", className)}>
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-danger-soft text-danger">
          <span className="text-lg font-bold" aria-hidden>
            !
          </span>
        </div>
        <p className="text-base font-semibold text-foreground">
          {title ?? tShell("errorTitle")}
        </p>
        <p className="text-sm text-danger">{message ?? t("errorWorkspace")}</p>
        <div className="flex flex-wrap items-center justify-center gap-2">
          {onRetry ? (
            <Button type="button" variant="secondary" size="sm" onClick={onRetry}>
              {tCommon("retry")}
            </Button>
          ) : null}
          <Link href="/apps">
            <Button type="button" variant="ghost" size="sm">
              {tShell("backToApps")}
            </Button>
          </Link>
        </div>
      </Panel>
    </div>
  );
}
