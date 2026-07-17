"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import {
  downloadInventoryBalancesReport,
  downloadInventoryTransactionsReport,
} from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
import { BranchPicker, WorkspaceHeader } from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Alert, Panel } from "@/components/ui/layout";

export default function InventoryReportsPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const canView = useAuthStore((s) => s.hasPermission("inventory.reports.view"));
  const [branchId, setBranchId] = useState("");
  const [branchLabel, setBranchLabel] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function download(kind: "balances" | "transactions") {
    if (!canView || busy) return;
    setBusy(true);
    setError(null);
    try {
      if (kind === "balances") await downloadInventoryBalancesReport(branchId || undefined);
      else await downloadInventoryTransactionsReport(branchId || undefined);
    } catch {
      setError(tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (!canView) {
    return (
      <div className="space-y-4" data-testid="permission-denied">
        <WorkspaceHeader title={t("reportsTitle")} subtitle={t("reportsSubtitle")} />
        <Alert tone="danger">{t("permissionDenied")}</Alert>
      </div>
    );
  }

  return (
    <div className="space-y-4" data-testid="inventory-reports">
      <WorkspaceHeader title={t("reportsTitle")} subtitle={t("reportsSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <Panel className="space-y-4 p-4">
        <div className="max-w-sm">
          <BranchPicker
            value={branchId || null}
            selectedLabel={branchLabel}
            onChange={(opt) => {
              setBranchId(opt ? String(opt.id) : "");
              setBranchLabel(opt?.label ?? null);
            }}
          />
        </div>
        <div className="flex flex-wrap gap-2">
          <Button disabled={busy} onClick={() => void download("balances")}>
            {t("actions.exportBalances")}
          </Button>
          <Button variant="secondary" disabled={busy} onClick={() => void download("transactions")}>
            {t("actions.exportTransactions")}
          </Button>
        </div>
      </Panel>
    </div>
  );
}
