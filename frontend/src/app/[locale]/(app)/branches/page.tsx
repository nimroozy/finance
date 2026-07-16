"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { createBranch, listBranches, updateBranch } from "@/lib/auth";
import { ApiError, apiFetch } from "@/lib/api";
import type { Branch, BranchPayload } from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Modal } from "@/components/ui/modal";
import { FieldError, Input, Label, TextArea } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

type PrefixMetrics = {
  prefixes: Array<{ normalized_prefix: string; active: boolean }>;
  matched_by_prefix: number;
  open_conflicts: number;
  unmapped_customers: number;
  last_prefix_mapping_run?: string | null;
};

const emptyForm: BranchPayload = {
  code: "",
  name_en: "",
  name_fa: "",
  province_en: "",
  province_fa: "",
  phone: "",
  address: "",
  receipt_prefix: "",
  is_active: true,
};

export default function BranchesPage() {
  const t = useTranslations("branches");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("branches.manage"));
  const canViewPrefixes = useAuthStore((s) =>
    s.hasPermission("customer_prefix_mapping.view"),
  );

  const [branches, setBranches] = useState<Branch[]>([]);
  const [metrics, setMetrics] = useState<Record<number, PrefixMetrics>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Branch | null>(null);
  const [form, setForm] = useState<BranchPayload>(emptyForm);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await listBranches(1);
      setBranches(res.data);
      if (canViewPrefixes) {
        const entries = await Promise.all(
          res.data.map(async (branch) => {
            try {
              const m = await apiFetch<PrefixMetrics>(
                `/branches/${branch.id}/prefix-metrics`,
              );
              return [branch.id, m.data] as const;
            } catch {
              return [branch.id, null] as const;
            }
          }),
        );
        const next: Record<number, PrefixMetrics> = {};
        for (const [id, m] of entries) {
          if (m) next[id] = m;
        }
        setMetrics(next);
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon, canViewPrefixes]);

  useEffect(() => {
    void load();
  }, [load]);

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setFormError(null);
    setFieldErrors({});
    setModalOpen(true);
  }

  function openEdit(branch: Branch) {
    setEditing(branch);
    setForm({
      code: branch.code,
      name_en: branch.name_en,
      name_fa: branch.name_fa,
      province_en: branch.province_en,
      province_fa: branch.province_fa,
      phone: branch.phone ?? "",
      address: branch.address ?? "",
      receipt_prefix: branch.receipt_prefix,
      is_active: branch.is_active,
    });
    setFormError(null);
    setFieldErrors({});
    setModalOpen(true);
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setFormError(null);
    setFieldErrors({});
    try {
      const payload: BranchPayload = {
        ...form,
        phone: form.phone || undefined,
        address: form.address || undefined,
      };
      if (editing) {
        await updateBranch(editing.id, payload);
        setSuccess(t("updateSuccess"));
      } else {
        await createBranch(payload);
        setSuccess(t("createSuccess"));
      }
      setModalOpen(false);
      await load();
    } catch (err) {
      if (err instanceof ApiError) {
        setFormError(err.message);
        setFieldErrors(err.errors);
      } else {
        setFormError(tCommon("error"));
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canManage ? (
            <Button onClick={openCreate}>{t("create")}</Button>
          ) : undefined
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
        ) : branches.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("code")}</th>
                  <th className="px-4 py-3 font-medium">
                    {locale === "fa" ? t("nameFa") : t("nameEn")}
                  </th>
                  <th className="px-4 py-3 font-medium">
                    {locale === "fa" ? t("provinceFa") : t("provinceEn")}
                  </th>
                  <th className="px-4 py-3 font-medium">{t("receiptPrefix")}</th>
                  {canViewPrefixes ? (
                    <th className="px-4 py-3 font-medium">
                      {locale === "fa" ? "پیشوند مشتری" : "Customer prefixes"}
                    </th>
                  ) : null}
                  <th className="px-4 py-3 font-medium">{t("isActive")}</th>
                  {canManage ? (
                    <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {branches.map((branch) => {
                  const m = metrics[branch.id];
                  const prefixes = (m?.prefixes || [])
                    .filter((p) => p.active)
                    .map((p) => p.normalized_prefix)
                    .join(", ");
                  return (
                    <tr
                      key={branch.id}
                      className="border-b border-border last:border-0"
                    >
                      <td className="px-4 py-3 font-medium">{branch.code}</td>
                      <td className="px-4 py-3">
                        {locale === "fa" ? branch.name_fa : branch.name_en}
                      </td>
                      <td className="px-4 py-3">
                        {locale === "fa"
                          ? branch.province_fa
                          : branch.province_en}
                      </td>
                      <td className="px-4 py-3">{branch.receipt_prefix}</td>
                      {canViewPrefixes ? (
                        <td className="px-4 py-3 text-xs text-muted">
                          <div>{prefixes || "—"}</div>
                          {m ? (
                            <div>
                              {locale === "fa" ? "تطبیق" : "Matched"}{" "}
                              {m.matched_by_prefix} ·{" "}
                              {locale === "fa" ? "تعارض" : "Conflicts"}{" "}
                              {m.open_conflicts} ·{" "}
                              {locale === "fa" ? "بدون نگاشت" : "Unmapped"}{" "}
                              {m.unmapped_customers}
                            </div>
                          ) : null}
                        </td>
                      ) : null}
                      <td className="px-4 py-3">
                        <Badge tone={branch.is_active ? "success" : "danger"}>
                          {branch.is_active
                            ? tCommon("active")
                            : tCommon("inactive")}
                        </Badge>
                      </td>
                      {canManage ? (
                        <td className="px-4 py-3">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openEdit(branch)}
                          >
                            {tCommon("edit")}
                          </Button>
                        </td>
                      ) : null}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      <Modal
        open={modalOpen}
        title={editing ? t("edit") : t("create")}
        onClose={() => setModalOpen(false)}
        wide
      >
        <form onSubmit={onSubmit} className="space-y-4">
          {formError ? <Alert>{formError}</Alert> : null}
          <div className="grid gap-4 sm:grid-cols-2">
            {(
              [
                ["code", t("code")],
                ["name_en", t("nameEn")],
                ["name_fa", t("nameFa")],
                ["province_en", t("provinceEn")],
                ["province_fa", t("provinceFa")],
                ["phone", t("phone")],
                ["receipt_prefix", t("receiptPrefix")],
              ] as const
            ).map(([key, label]) => (
              <div key={key}>
                <Label htmlFor={key}>{label}</Label>
                <Input
                  id={key}
                  value={String(form[key] ?? "")}
                  onChange={(e) =>
                    setForm({ ...form, [key]: e.target.value })
                  }
                  required={key !== "phone"}
                  disabled={Boolean(editing) && key === "code"}
                />
                <FieldError>{fieldErrors[key]?.[0]}</FieldError>
              </div>
            ))}
          </div>
          <div>
            <Label htmlFor="address">{t("address")}</Label>
            <TextArea
              id="address"
              value={form.address ?? ""}
              onChange={(e) => setForm({ ...form, address: e.target.value })}
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={Boolean(form.is_active)}
              onChange={(e) =>
                setForm({ ...form, is_active: e.target.checked })
              }
            />
            {t("isActive")}
          </label>
          <div className="flex justify-end gap-2">
            <Button
              variant="secondary"
              type="button"
              onClick={() => setModalOpen(false)}
            >
              {tCommon("cancel")}
            </Button>
            <Button type="submit" disabled={saving}>
              {editing ? tCommon("save") : tCommon("create")}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
