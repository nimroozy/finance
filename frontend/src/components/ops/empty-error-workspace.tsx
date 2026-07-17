"use client";

import { useTranslations } from "next-intl";
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
  className,
}: {
  message?: string;
  onRetry?: () => void;
  className?: string;
}) {
  const t = useTranslations("opsUi");
  const tCommon = useTranslations("common");
  return (
    <div role="alert">
      <Panel className={cn("space-y-4 p-8 text-center", className)}>
        <p className="text-sm text-danger">{message ?? t("errorWorkspace")}</p>
        {onRetry ? (
          <Button type="button" variant="secondary" size="sm" onClick={onRetry}>
            {tCommon("retry")}
          </Button>
        ) : null}
      </Panel>
    </div>
  );
}
