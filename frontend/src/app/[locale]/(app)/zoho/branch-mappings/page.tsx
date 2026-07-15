"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { listBranches } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import {
  createZohoBranchMapping,
  deleteZohoBranchMapping,
  listZohoBranchMappings,
  updateZohoBranchMapping,
} from "@/lib/zoho";
import type {
  Branch,
  ZohoBranchMapping,
  ZohoBranchMappingPayload,
  ZohoMappingMethod,
} from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Modal } from "@/components/ui/modal";
import { FieldError, Input, Label, Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

const METHODS: ZohoMappingMethod[] = [
  "zoho_branch",
  "zoho_location",
  "reporting_tag",
  "custom_field",
];

const emptyForm: ZohoBranchMappingPayload = {
  branch_id: 0,
  mapping_method: "zoho_branch",
  zoho_value: "",
  zoho_label: "",
  is_active: true,
};

export default function ZohoBranchMappingsPage() {
  const t = useTranslations("zohoBranchMappings");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canConfigure = useAuthStore((s) => s.hasPermission("zoho.configure"));

  const [mappings, setMappings] = useState<ZohoBranchMapping[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<ZohoBranchMapping | null>(null);
  const [form, setForm] = useState<ZohoBranchMappingPayload>(emptyForm);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [saving, setSaving] = useState(false);

  const [deleteTarget, setDeleteTarget] = useState<ZohoBranchMapping | null>(
    null,
  );
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [mapRes, branchRes] = await Promise.all([
        listZohoBranchMappings(),
        listBranches(1).catch(() => ({ data: [] as Branch[] })),
      ]);
      setMappings(mapRes.data);
      setBranches(branchRes.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  function openCreate() {
    setEditing(null);
    setForm({
      ...emptyForm,
      branch_id: branches[0]?.id ?? 0,
    });
    setFormError(null);
    setFieldErrors({});
    setModalOpen(true);
  }

  function openEdit(mapping: ZohoBranchMapping) {
    setEditing(mapping);
    setForm({
      branch_id: mapping.branch_id,
      mapping_method: mapping.mapping_method,
      zoho_value: mapping.zoho_value,
      zoho_label: mapping.zoho_label ?? "",
      is_active: mapping.is_active,
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
      if (editing) {
        await updateZohoBranchMapping(editing.id, {
          mapping_method: form.mapping_method,
          zoho_value: form.zoho_value.trim(),
          zoho_label: form.zoho_label?.trim() || null,
          is_active: form.is_active,
        });
        setSuccess(t("updateSuccess"));
      } else {
        await createZohoBranchMapping({
          ...form,
          zoho_value: form.zoho_value.trim(),
          zoho_label: form.zoho_label?.trim() || null,
        });
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

  async function confirmDelete() {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await deleteZohoBranchMapping(deleteTarget.id);
      setDeleteTarget(null);
      setSuccess(t("deleteSuccess"));
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setDeleteTarget(null);
    } finally {
      setDeleting(false);
    }
  }

  function methodLabel(method: string) {
    if (
      method === "zoho_branch" ||
      method === "zoho_location" ||
      method === "reporting_tag" ||
      method === "custom_field"
    ) {
      return t(`methods.${method}`);
    }
    return method;
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <div className="flex flex-wrap gap-2">
            <Link
              href="/zoho"
              className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm hover:bg-sand-soft"
            >
              {t("backToZoho")}
            </Link>
            {canConfigure ? (
              <Button onClick={openCreate}>{t("create")}</Button>
            ) : null}
          </div>
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
        ) : mappings.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("branch")}</th>
                  <th className="px-4 py-3 font-medium">{t("method")}</th>
                  <th className="px-4 py-3 font-medium">{t("zohoValue")}</th>
                  <th className="px-4 py-3 font-medium">{t("zohoLabel")}</th>
                  <th className="px-4 py-3 font-medium">{t("isActive")}</th>
                  {canConfigure ? (
                    <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {mappings.map((mapping) => (
                  <tr
                    key={mapping.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3">
                      {mapping.branch
                        ? `${mapping.branch.code} — ${
                            locale === "fa" && mapping.branch.name_fa
                              ? mapping.branch.name_fa
                              : mapping.branch.name_en
                          }`
                        : `#${mapping.branch_id}`}
                    </td>
                    <td className="px-4 py-3">
                      {methodLabel(mapping.mapping_method)}
                    </td>
                    <td className="px-4 py-3">{mapping.zoho_value}</td>
                    <td className="px-4 py-3">{mapping.zoho_label || "—"}</td>
                    <td className="px-4 py-3">
                      <Badge tone={mapping.is_active ? "success" : "danger"}>
                        {mapping.is_active
                          ? tCommon("active")
                          : tCommon("inactive")}
                      </Badge>
                    </td>
                    {canConfigure ? (
                      <td className="px-4 py-3">
                        <div className="flex gap-1">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openEdit(mapping)}
                          >
                            {tCommon("edit")}
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleteTarget(mapping)}
                          >
                            {tCommon("delete")}
                          </Button>
                        </div>
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      <Modal
        open={modalOpen}
        title={editing ? t("edit") : t("create")}
        onClose={() => setModalOpen(false)}
      >
        <form onSubmit={onSubmit} className="space-y-4">
          {formError ? <Alert>{formError}</Alert> : null}
          <div>
            <Label htmlFor="branch_id">{t("branch")}</Label>
            <Select
              id="branch_id"
              value={form.branch_id || ""}
              onChange={(e) =>
                setForm({ ...form, branch_id: Number(e.target.value) })
              }
              required
              disabled={Boolean(editing)}
            >
              <option value="">—</option>
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.code} —{" "}
                  {locale === "fa" ? branch.name_fa : branch.name_en}
                </option>
              ))}
            </Select>
            <FieldError>{fieldErrors.branch_id?.[0]}</FieldError>
          </div>
          <div>
            <Label htmlFor="mapping_method">{t("method")}</Label>
            <Select
              id="mapping_method"
              value={form.mapping_method}
              onChange={(e) =>
                setForm({
                  ...form,
                  mapping_method: e.target.value as ZohoMappingMethod,
                })
              }
              required
            >
              {METHODS.map((method) => (
                <option key={method} value={method}>
                  {t(`methods.${method}`)}
                </option>
              ))}
            </Select>
            <FieldError>{fieldErrors.mapping_method?.[0]}</FieldError>
          </div>
          <div>
            <Label htmlFor="zoho_value">{t("zohoValue")}</Label>
            <Input
              id="zoho_value"
              value={form.zoho_value}
              onChange={(e) =>
                setForm({ ...form, zoho_value: e.target.value })
              }
              required
            />
            <FieldError>{fieldErrors.zoho_value?.[0]}</FieldError>
          </div>
          <div>
            <Label htmlFor="zoho_label">{t("zohoLabel")}</Label>
            <Input
              id="zoho_label"
              value={form.zoho_label ?? ""}
              onChange={(e) =>
                setForm({ ...form, zoho_label: e.target.value })
              }
            />
            <FieldError>{fieldErrors.zoho_label?.[0]}</FieldError>
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

      <ConfirmDialog
        open={Boolean(deleteTarget)}
        title={tCommon("delete")}
        description={t("deleteConfirm")}
        confirmLabel={tCommon("delete")}
        danger
        loading={deleting}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void confirmDelete()}
      />
    </div>
  );
}
