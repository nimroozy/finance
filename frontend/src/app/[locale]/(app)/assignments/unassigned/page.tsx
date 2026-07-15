"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { bulkAssign, listUnassignedDebtors } from "@/lib/assignments";
import { listCollectors } from "@/lib/collectors";
import { listBranches } from "@/lib/auth";
import type { Branch, Collector, Customer } from "@/lib/types";
import { formatMoney } from "@/lib/utils";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import { Label, Select, TextArea } from "@/components/ui/form";
import { Modal } from "@/components/ui/modal";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function UnassignedDebtorsPage() {
  const t = useTranslations("assignments");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("assignments.manage"));

  const [rows, setRows] = useState<Customer[]>([]);
  const [selected, setSelected] = useState<number[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [branchId, setBranchId] = useState("");
  const [branches, setBranches] = useState<Branch[]>([]);
  const [collectors, setCollectors] = useState<Collector[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [collectorId, setCollectorId] = useState("");
  const [notes, setNotes] = useState("");
  const [saving, setSaving] = useState(false);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listUnassignedDebtors({
          page,
          branch_id: branchId || undefined,
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
    [branchId, tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  useEffect(() => {
    void Promise.all([
      listBranches(1).catch(() => ({ data: [] as Branch[] })),
      listCollectors({ is_active: true }).catch(() => ({
        data: [] as Collector[],
      })),
    ]).then(([b, c]) => {
      setBranches(b.data);
      setCollectors(c.data);
    });
  }, []);

  function toggle(id: number) {
    setSelected((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    );
  }

  function toggleAll() {
    if (selected.length === rows.length) {
      setSelected([]);
    } else {
      setSelected(rows.map((r) => r.id));
    }
  }

  async function onAssign(e: React.FormEvent) {
    e.preventDefault();
    if (!collectorId || selected.length === 0) return;
    setSaving(true);
    setError(null);
    try {
      await bulkAssign({
        customer_ids: selected,
        collector_id: Number(collectorId),
        branch_id: branchId ? Number(branchId) : undefined,
        notes: notes.trim() || undefined,
      });
      setModalOpen(false);
      setSelected([]);
      setSuccess(t("bulkSuccess"));
      await load(meta.current_page);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={t("unassigned")}
        subtitle={t("subtitle")}
        actions={
          canManage ? (
            <Button
              disabled={selected.length === 0}
              onClick={() => setModalOpen(true)}
            >
              {t("assignSelected")} ({selected.length})
            </Button>
          ) : null
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel className="mb-4 p-4">
        <Select
          value={branchId}
          onChange={(e) => setBranchId(e.target.value)}
          className="sm:max-w-xs"
        >
          <option value="">{tCommon("all")}</option>
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {b.code} — {locale === "fa" ? b.name_fa : b.name_en}
            </option>
          ))}
        </Select>
      </Panel>

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : rows.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[700px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3">
                    <input
                      type="checkbox"
                      checked={
                        rows.length > 0 && selected.length === rows.length
                      }
                      onChange={toggleAll}
                    />
                  </th>
                  <th className="px-4 py-3 font-medium">{t("customer")}</th>
                  <th className="px-4 py-3 font-medium">{t("outstanding")}</th>
                  <th className="px-4 py-3 font-medium">{t("branch")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={selected.includes(row.id)}
                        onChange={() => toggle(row.id)}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <Link
                        href={`/customers/${row.id}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {row.contact_name || "—"}
                      </Link>
                      <div className="text-xs text-muted">
                        {row.company_name || row.customer_number}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      {formatMoney(
                        row.outstanding_receivable,
                        row.currency,
                        locale,
                      )}
                    </td>
                    <td className="px-4 py-3">{row.branch?.code || "—"}</td>
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
                disabled={meta.current_page <= 1 || loading}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page || loading}
                onClick={() => void load(meta.current_page + 1)}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        ) : null}
      </Panel>

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={t("assignSelected")}
      >
        <form onSubmit={onAssign} className="space-y-4">
          <p className="text-sm text-muted">
            {t("selectedCount", { count: selected.length })}
          </p>
          <div>
            <Label>{t("collector")}</Label>
            <Select
              value={collectorId}
              onChange={(e) => setCollectorId(e.target.value)}
              required
            >
              <option value="">{t("selectCollector")}</option>
              {collectors.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.user?.name ?? `#${c.id}`}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label>{t("notes")}</Label>
            <TextArea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
          </div>
          <div className="flex justify-end gap-2">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setModalOpen(false)}
            >
              {tCommon("cancel")}
            </Button>
            <Button type="submit" disabled={saving}>
              {tCommon("confirm")}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
