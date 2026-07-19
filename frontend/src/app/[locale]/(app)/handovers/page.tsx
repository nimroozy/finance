"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import {
  approveCashHandover,
  getCashHandover,
  listCashHandovers,
  rejectCashHandover,
  type CashHandover,
} from "@/lib/handovers";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/form";
import { PageHeader } from "@/components/ui/layout";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";
import { DetailSection } from "@/components/ui/detail-section";
import { useToast } from "@/components/ui/toast";
import { ApiError } from "@/lib/api";

export default function ManagerHandoversPage() {
  const t = useTranslations("handoversPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const { show } = useToast();

  const [rows, setRows] = useState<CashHandover[]>([]);
  const [selected, setSelected] = useState<CashHandover | null>(null);
  const [counted, setCounted] = useState("");
  const [notes, setNotes] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listCashHandovers(1);
      setRows(res.data);
    } catch (err) {
      setError(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function openReview(id: number) {
    setBusy(true);
    try {
      const row = await getCashHandover(id);
      setSelected(row.data);
      setCounted(row.data.declared_amount);
      setNotes("");
    } catch (err) {
      show({ tone: "error", title: tCommon("error"), description: err instanceof ApiError ? err.message : undefined });
    } finally {
      setBusy(false);
    }
  }

  async function approve() {
    if (!selected) return;
    setBusy(true);
    try {
      await approveCashHandover(selected.id, {
        counted_amount: counted,
        approved_amount: counted,
        notes: notes || undefined,
      });
      show({ tone: "success", title: t("approveSuccess") });
      setSelected(null);
      await load();
    } catch (err) {
      show({ tone: "error", title: tCommon("error"), description: err instanceof ApiError ? err.message : undefined });
    } finally {
      setBusy(false);
    }
  }

  async function reject() {
    if (!selected) return;
    setBusy(true);
    try {
      await rejectCashHandover(selected.id, notes || "Rejected");
      show({ tone: "success", title: t("rejectSuccess") });
      setSelected(null);
      await load();
    } catch (err) {
      show({ tone: "error", title: tCommon("error"), description: err instanceof ApiError ? err.message : undefined });
    } finally {
      setBusy(false);
    }
  }

  const columns: DataTableColumn<CashHandover>[] = [
    {
      key: "collector",
      label: t("collector"),
      render: (row) => row.collector?.user?.name || `#${row.collector_id}`,
    },
    {
      key: "cashbox",
      label: t("cashbox"),
      render: (row) => row.cashbox?.name || "—",
    },
    {
      key: "amount",
      label: t("amount"),
      render: (row) => formatMoney(row.declared_amount, row.currency, locale),
    },
    {
      key: "paymentCount",
      label: t("paymentCount"),
      render: (row) => row.items?.length ?? 0,
    },
    { key: "status", label: t("status"), render: (row) => <StatusBadge status={row.status} /> },
    {
      key: "submitted",
      label: t("submittedAt"),
      render: (row) => formatDateTime(row.submitted_at, locale),
    },
    {
      key: "verified",
      label: t("verifiedAt"),
      render: (row) => formatDateTime(row.approved_at, locale),
    },
    {
      key: "actions",
      label: tCommon("actions"),
      render: (row) => (
        <Button variant="secondary" size="sm" disabled={busy} onClick={() => void openReview(row.id)}>
          {t("review")}
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? (
        <ErrorState error={error} onRetry={load} />
      ) : (
        <DataTable rows={rows} columns={columns} rowKey={(row) => row.id} loading={loading} emptyLabel={t("empty")} />
      )}

      {selected ? (
        <DetailSection title={`${t("reviewTitle")} #${selected.id}`}>
          <p className="text-sm">
            {t("selectedTotal")}: {formatMoney(selected.selected_payment_total, selected.currency, locale)}
          </p>
          <div className="mt-3">
            <Label>{t("countedAmount")}</Label>
            <Input value={counted} onChange={(e) => setCounted(e.target.value)} />
          </div>
          <div className="mt-3">
            <Label>{t("notes")}</Label>
            <Input value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
          <div className="mt-4 flex flex-wrap items-center gap-2">
            <Button disabled={busy || selected.status !== "submitted"} onClick={() => void approve()}>
              {t("approve")}
            </Button>
            <Button variant="secondary" disabled={busy || selected.status !== "submitted"} onClick={() => void reject()}>
              {t("reject")}
            </Button>
            <Link href="/cashboxes" className="text-sm text-primary hover:underline">
              {t("branchCashboxes")}
            </Link>
          </div>
        </DetailSection>
      ) : null}
    </div>
  );
}
