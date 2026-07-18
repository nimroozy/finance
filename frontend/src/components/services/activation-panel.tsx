"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import {
  ACTIVATION_CHECKLIST,
  type ActivationChecklist,
  type IspService,
} from "@/lib/services";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input, TextArea } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

type Props = {
  service: IspService;
  checklist: ActivationChecklist | null;
  canNocConfirm: boolean;
  canOverride: boolean;
  busy: boolean;
  onConfirmOnline: (reason: string) => Promise<void>;
  onOverrideActivate?: (reason: string) => Promise<void>;
};

export function ActivationPanel({
  service,
  checklist,
  canNocConfirm,
  canOverride,
  busy,
  onConfirmOnline,
  onOverrideActivate,
}: Props) {
  const t = useTranslations("services");
  const [nocReason, setNocReason] = useState("");
  const [overrideReason, setOverrideReason] = useState("");
  const [showOverride, setShowOverride] = useState(false);

  const showNoc =
    canNocConfirm &&
    service.commercial_status === "active" &&
    service.operational_status === "ready";

  const showActivateOverride =
    canOverride &&
    onOverrideActivate &&
    (service.commercial_status === "draft" || service.commercial_status === "pending_activation") &&
    (checklist?.missing?.length ?? 0) > 0;

  return (
    <Panel className="space-y-4 p-4" data-testid="activation-panel">
      <h3 className="text-sm font-semibold">{t("activationChecklistTitle")}</h3>
      <p className="text-xs text-muted">{t("activationEvidenceHint")}</p>

      <ul className="space-y-2 text-sm" data-testid="activation-checklist">
        {ACTIVATION_CHECKLIST.map((key) => {
          const ok = checklist?.checklist?.[key] === true;
          const evidence = checklist?.evidence?.[key];
          return (
            <li
              key={key}
              className="flex flex-col gap-1 rounded-md border border-border/60 px-3 py-2"
              data-testid={`checklist-item-${key}`}
            >
              <div className="flex items-center justify-between gap-2">
                <span>{t(`checklist.${key}` as "checklist.zoho_linked")}</span>
                <StatusBadge status={ok ? "ok" : "missing"} />
              </div>
              {evidence ? (
                <span className="text-xs text-muted" data-testid={`checklist-evidence-${key}`}>
                  {t("evidenceReadOnly")}: {summarizeEvidence(evidence)}
                </span>
              ) : null}
            </li>
          );
        })}
      </ul>

      {!checklist?.checklist ? (
        <p className="text-sm text-muted">{t("checklistIncomplete")}</p>
      ) : null}

      {showNoc ? (
        <form
          className="grid gap-2 border-t border-border pt-3"
          data-testid="noc-confirm-online"
          onSubmit={(e) => {
            e.preventDefault();
            if (!nocReason.trim()) return;
            void onConfirmOnline(nocReason.trim()).then(() => setNocReason(""));
          }}
        >
          <Alert>{t("nocConfirmHint")}</Alert>
          <Input
            data-testid="noc-online-reason"
            value={nocReason}
            onChange={(e) => setNocReason(e.target.value)}
            placeholder={t("fields.reason")}
            required
          />
          <Button type="submit" disabled={busy || !nocReason.trim()}>
            {t("actions.confirmOnline")}
          </Button>
        </form>
      ) : null}

      {showActivateOverride ? (
        <div className="grid gap-2 border-t border-border pt-3" data-testid="activation-override">
          {!showOverride ? (
            <Button
              type="button"
              variant="secondary"
              disabled={busy}
              onClick={() => setShowOverride(true)}
            >
              {t("actions.overrideActivate")}
            </Button>
          ) : (
            <form
              className="grid gap-2"
              onSubmit={(e) => {
                e.preventDefault();
                if (!overrideReason.trim() || !onOverrideActivate) return;
                void onOverrideActivate(overrideReason.trim()).then(() => {
                  setOverrideReason("");
                  setShowOverride(false);
                });
              }}
            >
              <Alert tone="danger">{t("overrideActivateHint")}</Alert>
              <TextArea
                data-testid="activation-override-reason"
                value={overrideReason}
                onChange={(e) => setOverrideReason(e.target.value)}
                placeholder={t("fields.reason")}
                rows={3}
                required
              />
              <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={busy || !overrideReason.trim()}>
                  {t("actions.confirmOverride")}
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={busy}
                  onClick={() => {
                    setShowOverride(false);
                    setOverrideReason("");
                  }}
                >
                  {t("actions.cancelOverride")}
                </Button>
              </div>
            </form>
          )}
        </div>
      ) : null}
    </Panel>
  );
}

function summarizeEvidence(evidence: Record<string, unknown>): string {
  const parts: string[] = [];
  for (const [k, v] of Object.entries(evidence)) {
    if (k === "ok" || v == null || v === "") continue;
    parts.push(`${k}=${String(v)}`);
  }
  return parts.length ? parts.join(", ") : "—";
}
