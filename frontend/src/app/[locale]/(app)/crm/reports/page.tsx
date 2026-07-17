"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { crmLeadsReportUrl } from "@/lib/crm";
import { useAuthStore } from "@/store/auth-store";
import { BranchPicker, UserPicker, WorkspaceHeader } from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";
import { PIPELINE_STAGES } from "@/lib/crm";

export default function CrmReportsPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const token = useAuthStore((s) => s.token);

  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);
  const [salespersonId, setSalespersonId] = useState("");
  const [salespersonLabel, setSalespersonLabel] = useState<string | null>(null);
  const [stage, setStage] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onExport() {
    setBusy(true);
    setError(null);
    try {
      const url = crmLeadsReportUrl({
        branch_id: branchId || undefined,
        pipeline_stage: stage || undefined,
        salesperson_id: salespersonId || undefined,
        from: from || undefined,
        to: to || undefined,
      });
      const res = await fetch(url, {
        headers: {
          Accept: "text/csv",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
      });
      if (!res.ok) throw new Error(t("exportFailed"));
      const blob = await res.blob();
      const href = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = href;
      a.download = "crm-leads-report.csv";
      a.click();
      URL.revokeObjectURL(href);
    } catch (err) {
      setError(err instanceof Error ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4">
      <WorkspaceHeader title={t("reportsTitle")} subtitle={t("reportsSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <Panel className="max-w-2xl space-y-4 p-4">
        <BranchPicker
          value={branchId || null}
          selectedLabel={branchLabel}
          onChange={(opt) => {
            setBranchId(opt ? String(opt.id) : "");
            setBranchLabel(opt?.label ?? null);
          }}
        />
        <UserPicker
          label={t("fields.salesperson")}
          value={salespersonId || null}
          selectedLabel={salespersonLabel}
          onChange={(opt) => {
            setSalespersonId(opt ? String(opt.id) : "");
            setSalespersonLabel(opt?.label ?? null);
          }}
        />
        <Select value={stage} onChange={(e) => setStage(e.target.value)}>
          <option value="">{t("allStages")}</option>
          {PIPELINE_STAGES.map((s) => (
            <option key={s} value={s}>
              {t(`stages.${s}` as "stages.lead")}
            </option>
          ))}
        </Select>
        <div className="grid gap-3 sm:grid-cols-2">
          <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
        <Button onClick={() => void onExport()} disabled={busy}>
          {busy ? tCommon("loading") : tCommon("export")}
        </Button>
      </Panel>
    </div>
  );
}
