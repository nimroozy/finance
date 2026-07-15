"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  createCollector,
  listCollectors,
  updateCollector,
} from "@/lib/collectors";
import type { Collector } from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input, Label, TextArea } from "@/components/ui/form";
import { Modal } from "@/components/ui/modal";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorsPage() {
  const t = useTranslations("collectorsPage");
  const tCommon = useTranslations("common");
  const canManage = useAuthStore((s) => s.hasPermission("collectors.manage"));

  const [rows, setRows] = useState<Collector[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [open, setOpen] = useState(false);
  const [userId, setUserId] = useState("");
  const [employeeCode, setEmployeeCode] = useState("");
  const [maxAssignments, setMaxAssignments] = useState("");
  const [notes, setNotes] = useState("");
  const [saving, setSaving] = useState(false);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listCollectors({ page, per_page: 15 });
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
    [tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  async function onCreate(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      await createCollector({
        user_id: Number(userId),
        employee_code: employeeCode.trim() || undefined,
        max_active_assignments: maxAssignments
          ? Number(maxAssignments)
          : undefined,
        notes: notes.trim() || undefined,
      });
      setOpen(false);
      setSuccess(t("createSuccess"));
      setUserId("");
      setEmployeeCode("");
      setMaxAssignments("");
      setNotes("");
      await load(1);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  async function toggleActive(c: Collector) {
    try {
      await updateCollector(c.id, { is_active: !c.is_active });
      setSuccess(t("updateSuccess"));
      await load(meta.current_page);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    }
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canManage ? (
            <Button onClick={() => setOpen(true)}>{t("create")}</Button>
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
                  <th className="px-4 py-3 font-medium">{t("name")}</th>
                  <th className="px-4 py-3 font-medium">{t("email")}</th>
                  <th className="px-4 py-3 font-medium">{t("employeeCode")}</th>
                  <th className="px-4 py-3 font-medium">{t("assignments")}</th>
                  <th className="px-4 py-3 font-medium">{t("active")}</th>
                  {canManage ? (
                    <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr
                    key={row.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3 font-medium">
                      {row.user?.name || `#${row.id}`}
                    </td>
                    <td className="px-4 py-3">{row.user?.email || "—"}</td>
                    <td className="px-4 py-3">{row.employee_code || "—"}</td>
                    <td className="px-4 py-3">
                      {row.active_assignments_count ?? "—"}
                    </td>
                    <td className="px-4 py-3">
                      <Badge tone={row.is_active ? "success" : "neutral"}>
                        {row.is_active ? tCommon("active") : tCommon("inactive")}
                      </Badge>
                    </td>
                    {canManage ? (
                      <td className="px-4 py-3">
                        <Button
                          size="sm"
                          variant="secondary"
                          onClick={() => void toggleActive(row)}
                        >
                          {row.is_active ? t("deactivate") : t("activate")}
                        </Button>
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      <Modal open={open} onClose={() => setOpen(false)} title={t("create")}>
        <form onSubmit={onCreate} className="space-y-4">
          <div>
            <Label>{t("userId")}</Label>
            <Input
              type="number"
              value={userId}
              onChange={(e) => setUserId(e.target.value)}
              required
            />
          </div>
          <div>
            <Label>{t("employeeCode")}</Label>
            <Input
              value={employeeCode}
              onChange={(e) => setEmployeeCode(e.target.value)}
            />
          </div>
          <div>
            <Label>{t("maxAssignments")}</Label>
            <Input
              type="number"
              value={maxAssignments}
              onChange={(e) => setMaxAssignments(e.target.value)}
            />
          </div>
          <div>
            <Label>{t("notes")}</Label>
            <TextArea value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
          <div className="flex justify-end gap-2">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setOpen(false)}
            >
              {tCommon("cancel")}
            </Button>
            <Button type="submit" disabled={saving}>
              {tCommon("create")}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
