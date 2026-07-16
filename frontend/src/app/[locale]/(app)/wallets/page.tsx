"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import { listBranches } from "@/lib/auth";
import { listCollectors } from "@/lib/collectors";
import {
  getCollectorWallet,
  listWalletTransactions,
} from "@/lib/wallets";
import type {
  Branch,
  Collector,
  CollectorWallet,
  CollectorWalletTransaction,
} from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function ManagerWalletsPage() {
  const t = useTranslations("walletsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [collectors, setCollectors] = useState<Collector[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [collectorId, setCollectorId] = useState("");
  const [branchId, setBranchId] = useState("");
  const [wallet, setWallet] = useState<CollectorWallet | null>(null);
  const [rows, setRows] = useState<CollectorWalletTransaction[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void Promise.all([listCollectors({ per_page: 100 }), listBranches(1)])
      .then(([c, b]) => {
        setCollectors(c.data);
        setBranches(b.data);
        if (c.data[0]) setCollectorId(String(c.data[0].id));
        if (b.data[0]) setBranchId(String(b.data[0].id));
      })
      .catch(() => undefined);
  }, []);

  async function load() {
    if (!collectorId || !branchId) return;
    setLoading(true);
    setError(null);
    try {
      const [w, tx] = await Promise.all([
        getCollectorWallet({
          collector_id: collectorId,
          branch_id: branchId,
        }),
        listWalletTransactions({
          collector_id: collectorId,
          branch_id: branchId,
          per_page: 30,
        }),
      ]);
      setWallet(w.data);
      setRows(tx.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setWallet(null);
      setRows([]);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {error ? <Alert>{error}</Alert> : null}

      <Panel className="grid gap-3 p-4 sm:grid-cols-3">
        <Select
          value={collectorId}
          onChange={(e) => setCollectorId(e.target.value)}
        >
          <option value="">{t("collectorId")}</option>
          {collectors.map((c) => (
            <option key={c.id} value={c.id}>
              {c.user?.name || c.employee_code || `#${c.id}`}
            </option>
          ))}
        </Select>
        <Select value={branchId} onChange={(e) => setBranchId(e.target.value)}>
          <option value="">{tCommon("branch")}</option>
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {locale === "fa" ? b.name_fa || b.name_en : b.name_en}
            </option>
          ))}
        </Select>
        <Button onClick={() => void load()} disabled={loading}>
          {t("load")}
        </Button>
      </Panel>

      {loading ? <LoadingState label={tCommon("loading")} /> : null}

      {wallet ? (
        <div className="grid grid-cols-2 gap-3 sm:max-w-lg">
          <Panel className="p-4">
            <p className="text-xs text-muted">{t("balance")}</p>
            <p className="mt-1 text-2xl font-semibold text-primary">
              {formatMoney(wallet.balance, wallet.currency, locale)}
            </p>
          </Panel>
          <Panel className="p-4">
            <p className="text-xs text-muted">{t("pendingHandover")}</p>
            <p className="mt-1 text-2xl font-semibold">
              {formatMoney(
                wallet.pending_handover_balance,
                wallet.currency,
                locale,
              )}
            </p>
          </Panel>
        </div>
      ) : null}

      <Panel>
        <div className="border-b border-border px-4 py-3 font-medium">
          {t("transactions")}
        </div>
        {!loading && rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <ul className="divide-y divide-border">
            {rows.map((row) => (
              <li key={row.id} className="flex justify-between gap-3 px-4 py-3 text-sm">
                <div>
                  <p className="font-medium">{row.type}</p>
                  <p className="text-xs text-muted">
                    {formatDateTime(row.created_at, locale)}
                  </p>
                </div>
                <p>{formatMoney(row.amount, row.currency, locale)}</p>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
