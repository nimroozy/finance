"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listBranches } from "@/lib/auth";
import {
  TICKET_PRIORITIES,
  TICKET_STATUSES,
  listTickets,
  type Ticket,
} from "@/lib/tickets";
import type { Branch } from "@/lib/types";
import { formatDateTime } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function TicketsPage() {
  const t = useTranslations("tickets");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canCreate = useAuthStore((s) => s.hasPermission("tickets.create"));

  const [rows, setRows] = useState<Ticket[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [status, setStatus] = useState("");
  const [priority, setPriority] = useState("");
  const [branchId, setBranchId] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listBranches(1)
      .then((res) => setBranches(res.data))
      .catch(() => setBranches([]));
  }, []);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listTickets({
          page,
          status: status || undefined,
          priority: priority || undefined,
          branch_id: branchId || undefined,
        });
        let data = res.data;
        if (priority) {
          data = data.filter((row) => row.priority === priority);
        }
        setRows(data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err instanceof ApiError ? err.message : tCommon("error"));
      } finally {
        setLoading(false);
      }
    },
    [status, priority, branchId, tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canCreate ? (
            <Link href="/tickets/new">
              <Button>{t("newTicket")}</Button>
            </Link>
          ) : null
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 grid gap-3 p-4 sm:grid-cols-4">
        <Select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">{tCommon("all")}</option>
          {TICKET_STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`status_${s}` as "status_new")}
            </option>
          ))}
        </Select>
        <Select value={priority} onChange={(e) => setPriority(e.target.value)}>
          <option value="">{t("priority")}</option>
          {TICKET_PRIORITIES.map((p) => (
            <option key={p} value={p}>
              {t(`priority_${p}` as "priority_low")}
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
        <Button variant="secondary" onClick={() => void load(1)}>
          {tCommon("apply")}
        </Button>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("number")}</th>
                  <th className="px-4 py-3 font-medium">{t("subject")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("priority")}</th>
                  <th className="px-4 py-3 font-medium">{t("assignee")}</th>
                  <th className="px-4 py-3 font-medium">{t("createdAt")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3">
                      <Link
                        href={`/tickets/${row.id}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {row.ticket_number}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{row.subject}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3">{row.priority}</td>
                    <td className="px-4 py-3">
                      {row.primary_assignee?.name || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {formatDateTime(row.created_at, locale)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", {
                page: meta.current_page,
                total: meta.last_page,
              })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page <= 1}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => void load(meta.current_page + 1)}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        ) : null}
      </Panel>
    </div>
  );
}
