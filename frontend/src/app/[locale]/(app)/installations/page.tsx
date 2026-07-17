"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  INSTALLATION_STATUSES,
  listInstallations,
  type Installation,
} from "@/lib/installations";
import { formatDateTime } from "@/lib/utils";
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

export default function InstallationsPage() {
  const t = useTranslations("installations");
  const tCommon = useTranslations("common");
  const locale = useLocale();

  const [rows, setRows] = useState<Installation[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [status, setStatus] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listInstallations({
          page,
          status: status || undefined,
        });
        setRows(res.data);
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
    [status, tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      <Panel className="mb-4 flex flex-wrap gap-3 p-4">
        <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:max-w-xs">
          <option value="">{tCommon("all")}</option>
          {INSTALLATION_STATUSES.map((s) => (
            <option key={s} value={s}>
              {s.replace(/_/g, " ")}
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
            <table className="w-full min-w-[800px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("number")}</th>
                  <th className="px-4 py-3 font-medium">{t("contact")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  <th className="px-4 py-3 font-medium">{t("package")}</th>
                  <th className="px-4 py-3 font-medium">{t("createdAt")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3">
                      <Link
                        href={`/installations/${row.id}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {row.installation_number}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      {row.contact_name || row.prospect_name || row.phone || "—"}
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3">{row.requested_package || "—"}</td>
                    <td className="px-4 py-3">{formatDateTime(row.created_at, locale)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", { page: meta.current_page, total: meta.last_page })}
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
