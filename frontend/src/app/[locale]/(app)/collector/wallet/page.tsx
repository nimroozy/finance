"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  getCollectorWallet,
  listWalletTransactions,
} from "@/lib/wallets";
import type {
  CollectorWallet,
  CollectorWalletTransaction,
} from "@/lib/types";
import { formatDateTime, formatMoney } from "@/lib/utils";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";
import { Button } from "@/components/ui/button";

export default function CollectorWalletPage() {
  const t = useTranslations("walletsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [wallet, setWallet] = useState<CollectorWallet | null>(null);
  const [rows, setRows] = useState<CollectorWalletTransaction[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const [w, tx] = await Promise.all([
          getCollectorWallet(),
          listWalletTransactions({ page, per_page: 20 }),
        ]);
        setWallet(w.data);
        setRows(tx.data);
        setMeta({
          current_page: tx.meta?.current_page ?? 1,
          last_page: tx.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err instanceof ApiError ? err.message : tCommon("error"));
      } finally {
        setLoading(false);
      }
    },
    [tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  if (loading && !wallet) return <LoadingState label={tCommon("loading")} />;

  return (
    <div className="space-y-4">
      <PageHeader title={t("myTitle")} subtitle={t("mySubtitle")} />
      {error ? <Alert>{error}</Alert> : null}

      {wallet ? (
        <div className="grid grid-cols-2 gap-3">
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
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <ul className="divide-y divide-border">
            {rows.map((row) => (
              <li key={row.id} className="px-4 py-3 text-sm">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium">{row.type}</span>
                  <span>
                    {formatMoney(row.amount, row.currency, locale)}
                  </span>
                </div>
                <p className="text-xs text-muted">
                  {row.reference || "—"} ·{" "}
                  {formatDateTime(row.created_at, locale)}
                </p>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      {meta.last_page > 1 ? (
        <div className="flex items-center justify-between gap-3">
          <Button
            variant="secondary"
            disabled={meta.current_page <= 1 || loading}
            onClick={() => void load(meta.current_page - 1)}
          >
            {tCommon("previous")}
          </Button>
          <Button
            variant="secondary"
            disabled={meta.current_page >= meta.last_page || loading}
            onClick={() => void load(meta.current_page + 1)}
          >
            {tCommon("next")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
