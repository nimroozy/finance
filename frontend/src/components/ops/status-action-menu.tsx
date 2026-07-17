"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { cn } from "@/lib/utils";

export type StatusTransition = {
  id: string;
  label: string;
  danger?: boolean;
};

export function StatusActionMenu({
  transitions,
  onTransition,
  loading,
  className,
  confirmDescription,
}: {
  transitions: StatusTransition[];
  onTransition: (id: string) => void | Promise<void>;
  loading?: boolean;
  className?: string;
  confirmDescription?: string;
}) {
  const t = useTranslations("opsUi");
  const tCommon = useTranslations("common");
  const [pending, setPending] = useState<StatusTransition | null>(null);
  const [busy, setBusy] = useState(false);

  if (!transitions.length) {
    return (
      <p className={cn("text-sm text-muted", className)}>{t("noTransitions")}</p>
    );
  }

  async function confirm() {
    if (!pending) return;
    setBusy(true);
    try {
      await onTransition(pending.id);
      setPending(null);
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <div className={cn("flex flex-wrap gap-2", className)} role="group" aria-label={t("statusActions")}>
        {transitions.map((tr) => (
          <Button
            key={tr.id}
            type="button"
            size="sm"
            variant={tr.danger ? "danger" : "secondary"}
            disabled={loading || busy}
            onClick={() => setPending(tr)}
          >
            {tr.label}
          </Button>
        ))}
      </div>
      <ConfirmDialog
        open={Boolean(pending)}
        title={pending?.label ?? t("confirmTransition")}
        description={
          confirmDescription ??
          t("confirmTransitionDesc", { status: pending?.label ?? "" })
        }
        confirmLabel={tCommon("confirm")}
        danger={pending?.danger}
        loading={busy}
        onCancel={() => setPending(null)}
        onConfirm={() => void confirm()}
      />
    </>
  );
}
