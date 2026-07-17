"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { TextArea } from "@/components/ui/form";
import { cn } from "@/lib/utils";

export type StatusTransition = {
  id: string;
  label: string;
  danger?: boolean;
  requiresReason?: boolean;
};

export function StatusActionMenu({
  transitions,
  onTransition,
  loading,
  className,
  confirmDescription,
  large,
}: {
  transitions: StatusTransition[];
  onTransition: (id: string, reason?: string) => void | Promise<void>;
  loading?: boolean;
  className?: string;
  confirmDescription?: string;
  large?: boolean;
}) {
  const t = useTranslations("opsUi");
  const tCommon = useTranslations("common");
  const [pending, setPending] = useState<StatusTransition | null>(null);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);

  if (!transitions.length) {
    return (
      <p className={cn("text-sm text-muted", className)}>{t("noTransitions")}</p>
    );
  }

  async function confirm() {
    if (!pending) return;
    if (pending.requiresReason && !reason.trim()) return;
    setBusy(true);
    try {
      await onTransition(pending.id, reason.trim() || undefined);
      setPending(null);
      setReason("");
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <div
        className={cn(
          "flex flex-wrap gap-2",
          large && "flex-col sm:flex-row",
          className,
        )}
        role="group"
        aria-label={t("statusActions")}
      >
        {transitions.map((tr) => (
          <Button
            key={tr.id}
            type="button"
            size={large ? "md" : "sm"}
            variant={tr.danger ? "danger" : large ? "primary" : "secondary"}
            className={large ? "h-14 w-full text-base sm:w-auto" : undefined}
            disabled={loading || busy}
            onClick={() => {
              setReason("");
              setPending(tr);
            }}
          >
            {tr.label}
          </Button>
        ))}
      </div>

      {pending ? (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center bg-primary/40 p-4 sm:items-center"
          role="presentation"
          onClick={() => {
            if (!busy) {
              setPending(null);
              setReason("");
            }
          }}
        >
          <div
            role="alertdialog"
            aria-modal="true"
            className="w-full max-w-md rounded-lg border border-border bg-surface-elevated p-5 shadow-[var(--shadow)]"
            onClick={(e) => e.stopPropagation()}
          >
            <h2 className="text-lg font-semibold">{pending.label}</h2>
            <p className="mt-2 text-sm text-muted">
              {confirmDescription ??
                t("confirmTransitionDesc", { status: pending.label })}
            </p>
            {pending.requiresReason ? (
              <label className="mt-4 grid gap-1 text-sm">
                <span>{t("reasonRequired")}</span>
                <TextArea
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  rows={3}
                  autoFocus
                />
              </label>
            ) : null}
            <div className="mt-5 flex justify-end gap-2">
              <Button
                type="button"
                variant="secondary"
                disabled={busy}
                onClick={() => {
                  setPending(null);
                  setReason("");
                }}
              >
                {tCommon("cancel")}
              </Button>
              <Button
                type="button"
                variant={pending.danger ? "danger" : "primary"}
                disabled={busy || (pending.requiresReason && !reason.trim())}
                onClick={() => void confirm()}
              >
                {tCommon("confirm")}
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
