"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  getStockCount,
  postStockCount,
  recordStockCount,
  type StockCount,
} from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
import { ErrorWorkspace, WorkspaceHeader } from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function StockCountDetailPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id);
  const canCount = useAuthStore((s) => s.hasPermission("inventory.stock.count"));

  const [item, setItem] = useState<StockCount | null>(null);
  const [counts, setCounts] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getStockCount(id);
      setItem(res.data);
      const next: Record<string, string> = {};
      for (const line of res.data.lines || []) {
        next[String(line.product_id)] =
          line.counted_qty != null ? String(line.counted_qty) : "";
      }
      setCounts(next);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setItem(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onRecord() {
    if (!canCount || busy) return;
    setBusy(true);
    setError(null);
    try {
      const payload: Record<string, number> = {};
      for (const [k, v] of Object.entries(counts)) {
        if (v !== "") payload[k] = Number(v);
      }
      const res = await recordStockCount(id, payload);
      setItem(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onPost() {
    if (!canCount || busy) return;
    setBusy(true);
    setError(null);
    try {
      const res = await postStockCount(id);
      setItem(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4" data-testid="count-detail">
      <WorkspaceHeader
        title={item?.count_number || t("countsTitle")}
        subtitle={t("countsSubtitle")}
        actions={
          <Link href="/inventory/counts">
            <Button variant="secondary">{t("backToCounts")}</Button>
          </Link>
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {error && !item ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {item ? (
        <Panel className="space-y-4 p-4">
          <StatusBadge status={item.status} />
          <ul className="space-y-3">
            {(item.lines || []).map((line) => (
              <li key={line.id} className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-[1fr_auto]">
                <div>
                  <p className="font-medium">{line.product?.name_en || `#${line.product_id}`}</p>
                  <p className="text-xs text-muted">
                    {t("columns.expected")}: {line.expected_qty ?? "—"}
                  </p>
                </div>
                <Input
                  type="number"
                  className="min-h-12 w-full sm:w-32"
                  value={counts[String(line.product_id)] ?? ""}
                  onChange={(e) =>
                    setCounts((c) => ({ ...c, [String(line.product_id)]: e.target.value }))
                  }
                  aria-label={t("columns.counted")}
                />
              </li>
            ))}
          </ul>
          {canCount ? (
            <div className="flex flex-col gap-2 sm:flex-row">
              <Button className="min-h-14 flex-1 text-base" disabled={busy} onClick={() => void onRecord()}>
                {t("actions.recordCount")}
              </Button>
              <Button
                className="min-h-14 flex-1 text-base"
                variant="secondary"
                disabled={busy}
                onClick={() => void onPost()}
              >
                {t("actions.postCount")}
              </Button>
            </div>
          ) : null}
        </Panel>
      ) : null}
    </div>
  );
}
