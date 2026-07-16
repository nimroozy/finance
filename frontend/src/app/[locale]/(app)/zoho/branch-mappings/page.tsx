"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listBranches } from "@/lib/auth";
import {
  applyZohoAutoMatch,
  createZohoBranchMapping,
  deleteZohoBranchMapping,
  importZohoLocationAsBranch,
  linkBranchLocation,
  listZohoBranchMappings,
  listZohoLocations,
  listZohoReportingTags,
  previewZohoAutoMatch,
  syncZohoStructure,
  updateZohoBranchMapping,
} from "@/lib/zoho";
import type {
  Branch,
  ZohoAutoMatchResult,
  ZohoBranchMapping,
  ZohoBranchMappingPayload,
  ZohoLocation,
  ZohoMappingMethod,
  ZohoReportingTag,
} from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FieldError, Input, Label, Select } from "@/components/ui/form";
import { Modal } from "@/components/ui/modal";
import { Alert, EmptyState, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

const METHODS: ZohoMappingMethod[] = ["zoho_location", "reporting_tag", "zoho_branch", "custom_field"];
const emptyForm: ZohoBranchMappingPayload = {
  branch_id: 0,
  mapping_method: "zoho_location",
  zoho_value: "",
  zoho_label: "",
  is_active: true,
};

export default function ZohoBranchMappingsPage() {
  const t = useTranslations("zohoBranchMappings");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canConfigure = useAuthStore((s) => s.hasPermission("zoho.configure"));
  const user = useAuthStore((s) => s.user);
  const showAdvanced = Boolean(
    user?.roles.includes("Super Administrator") ||
    user?.permissions.includes("*") ||
    user?.permissions.includes("all"),
  );
  const [mappings, setMappings] = useState<ZohoBranchMapping[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [locations, setLocations] = useState<ZohoLocation[]>([]);
  const [tags, setTags] = useState<ZohoReportingTag[]>([]);
  const [preview, setPreview] = useState<ZohoAutoMatchResult[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<ZohoBranchMapping | null>(null);
  const [form, setForm] = useState<ZohoBranchMappingPayload>(emptyForm);
  const [tagId, setTagId] = useState("");
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [applyOpen, setApplyOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<ZohoBranchMapping | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [mappingRes, branchRes, locationRes, tagRes] = await Promise.all([
        listZohoBranchMappings(),
        listBranches(1),
        listZohoLocations(),
        listZohoReportingTags(),
      ]);
      setMappings(mappingRes.data);
      setBranches(branchRes.data);
      setLocations(locationRes.data);
      setTags(tagRes.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => { void load(); }, [load]);

  const selectedTag = tags.find((tag) => tag.zoho_tag_id === tagId);
  const unlinkedLocations = useMemo(() => {
    const linked = new Set([
      ...branches.map((branch) => branch.zoho_location_id).filter(Boolean),
      ...mappings.filter((mapping) => mapping.mapping_method === "zoho_location").map((mapping) => mapping.zoho_value),
    ]);
    return locations.filter((location) => !linked.has(location.zoho_location_id));
  }, [branches, locations, mappings]);

  function openCreate() {
    setEditing(null);
    setTagId("");
    setForm({ ...emptyForm, branch_id: branches[0]?.id ?? 0 });
    setFormError(null);
    setFieldErrors({});
    setModalOpen(true);
  }

  function openEdit(mapping: ZohoBranchMapping) {
    setEditing(mapping);
    const ownerTag = tags.find((tag) => tag.options.some((option) => option.zoho_tag_option_id === mapping.zoho_value));
    setTagId(ownerTag?.zoho_tag_id ?? "");
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

  async function run(action: () => Promise<unknown>, message: string, refresh = true) {
    setBusy(true);
    setError(null);
    try {
      await action();
      setSuccess(message);
      if (refresh) await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setFormError(null);
    setFieldErrors({});
    try {
      const payload = { ...form, zoho_value: form.zoho_value.trim(), zoho_label: form.zoho_label?.trim() || null };
      if (editing) {
        await updateZohoBranchMapping(editing.id, payload);
      } else {
        await createZohoBranchMapping(payload);
      }
      if (payload.mapping_method === "zoho_location") {
        await linkBranchLocation(payload.branch_id, payload.zoho_value);
      }
      setModalOpen(false);
      setSuccess(editing ? t("updateSuccess") : t("createSuccess"));
      await load();
    } catch (err) {
      if (err instanceof ApiError) {
        setFormError(err.message);
        setFieldErrors(err.errors);
      } else setFormError(tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  function changeMethod(method: ZohoMappingMethod) {
    setTagId("");
    setForm({ ...form, mapping_method: method, zoho_value: "", zoho_label: "" });
  }

  function selectLocation(id: string) {
    const location = locations.find((item) => item.zoho_location_id === id);
    setForm({ ...form, zoho_value: id, zoho_label: location?.name ?? "" });
  }

  function selectTagOption(id: string) {
    const option = selectedTag?.options.find((item) => item.zoho_tag_option_id === id);
    setForm({ ...form, zoho_value: id, zoho_label: option?.name ?? "" });
  }

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} actions={
        <div className="flex flex-wrap gap-2">
          <Link href="/zoho" className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm hover:bg-sand-soft">{t("backToZoho")}</Link>
          {canConfigure ? <Button onClick={openCreate}>{t("create")}</Button> : null}
        </div>
      } />
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      {success ? <div className="mb-4"><Alert tone="success">{success}</Alert></div> : null}

      {canConfigure ? <Panel className="mb-6 p-4">
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" disabled={busy} onClick={() => void run(syncZohoStructure, t("structureQueued"))}>{t("fetchStructure")}</Button>
          <Button variant="secondary" disabled={busy} onClick={() => void run(async () => { const result = await previewZohoAutoMatch(); setPreview(result.data); }, t("previewReady"), false)}>{t("previewAutoMatch")}</Button>
          <Button disabled={busy || preview.length === 0} onClick={() => setApplyOpen(true)}>{t("applyAutoMatch")}</Button>
        </div>
      </Panel> : null}

      {preview.length > 0 ? <Panel className="mb-6 p-4">
        <h2 className="mb-3 text-sm font-medium">{t("previewResults")}</h2>
        <div className="grid gap-2 sm:grid-cols-2">
          {preview.map((item) => {
            const branch = branches.find((entry) => entry.id === item.branch_id);
            return <div key={item.branch_id} className="flex items-center justify-between rounded-md border border-border p-3 text-sm"><span>{branch?.name_en ?? `#${item.branch_id}`} → {item.location_name ?? "—"}</span><Badge tone={item.matched ? "success" : "warning"}>{item.matched ? t("matched") : t("unmatched")}</Badge></div>;
          })}
        </div>
      </Panel> : null}

      <Panel className="mb-6">
        {loading ? <LoadingState label={tCommon("loading")} /> : mappings.length === 0 ? <EmptyState label={tCommon("empty")} /> : (
          <div className="overflow-x-auto"><table className="w-full min-w-[980px] text-sm">
            <thead className="border-b border-border bg-sand-soft/40 text-muted"><tr>
              <th className="px-4 py-3 text-start">{t("branch")}</th><th className="px-4 py-3 text-start">{t("method")}</th><th className="px-4 py-3 text-start">{t("zohoLabel")}</th><th className="px-4 py-3 text-start">{t("customers")}</th><th className="px-4 py-3 text-start">{t("invoices")}</th><th className="px-4 py-3 text-start">{t("syncStatus")}</th><th className="px-4 py-3 text-start">{t("isActive")}</th>{canConfigure ? <th className="px-4 py-3 text-start">{tCommon("actions")}</th> : null}
            </tr></thead>
            <tbody>{mappings.map((mapping) => <tr key={mapping.id} className="border-b border-border last:border-0">
              <td className="px-4 py-3">{mapping.branch ? `${mapping.branch.code} — ${locale === "fa" ? mapping.branch.name_fa : mapping.branch.name_en}` : `#${mapping.branch_id}`}</td>
              <td className="px-4 py-3">{t(`methods.${mapping.mapping_method}`)}</td><td className="px-4 py-3">{mapping.zoho_label || "—"}</td>
              <td className="px-4 py-3">{mapping.mapped_customer_count ?? 0}</td><td className="px-4 py-3">{mapping.mapped_invoice_count ?? 0}</td>
              <td className="px-4 py-3"><Badge tone={mapping.branch?.zoho_sync_status === "linked" ? "success" : "neutral"}>{mapping.branch?.zoho_sync_status ?? "—"}</Badge></td>
              <td className="px-4 py-3"><Badge tone={mapping.is_active ? "success" : "danger"}>{mapping.is_active ? tCommon("active") : tCommon("inactive")}</Badge></td>
              {canConfigure ? <td className="px-4 py-3"><div className="flex gap-1"><Button size="sm" variant="ghost" onClick={() => openEdit(mapping)}>{tCommon("edit")}</Button><Button size="sm" variant="ghost" onClick={() => setDeleteTarget(mapping)}>{tCommon("delete")}</Button></div></td> : null}
            </tr>)}</tbody>
          </table></div>
        )}
      </Panel>

      {canConfigure && unlinkedLocations.length > 0 ? <Panel className="p-4">
        <h2 className="mb-3 text-sm font-medium">{t("unlinkedLocations")}</h2>
        <div className="grid gap-2 sm:grid-cols-2">{unlinkedLocations.map((location) => <div key={location.zoho_location_id} className="flex items-center justify-between rounded-md border border-border p-3"><span className="text-sm">{location.name}{location.code ? ` (${location.code})` : ""}</span><Button size="sm" variant="secondary" disabled={busy} onClick={() => void run(() => importZohoLocationAsBranch(location.zoho_location_id), t("importSuccess"))}>{t("importBranch")}</Button></div>)}</div>
      </Panel> : null}

      <Modal open={modalOpen} title={editing ? t("edit") : t("create")} onClose={() => setModalOpen(false)}>
        <form onSubmit={onSubmit} className="space-y-4">
          {formError ? <Alert>{formError}</Alert> : null}
          <div><Label htmlFor="branch_id">{t("branch")}</Label><Select id="branch_id" value={form.branch_id || ""} disabled={Boolean(editing)} required onChange={(e) => setForm({ ...form, branch_id: Number(e.target.value) })}><option value="">—</option>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.code} — {locale === "fa" ? branch.name_fa : branch.name_en}</option>)}</Select><FieldError>{fieldErrors.branch_id?.[0]}</FieldError></div>
          <div><Label htmlFor="mapping_method">{t("method")}</Label><Select id="mapping_method" value={form.mapping_method} onChange={(e) => changeMethod(e.target.value as ZohoMappingMethod)}>{METHODS.map((method) => <option key={method} value={method}>{t(`methods.${method}`)}</option>)}</Select></div>
          {form.mapping_method === "zoho_location" ? <div><Label htmlFor="location">{t("location")}</Label><Select id="location" value={form.zoho_value} required onChange={(e) => selectLocation(e.target.value)}><option value="">—</option>{locations.map((location) => <option key={location.zoho_location_id} value={location.zoho_location_id}>{location.name}{location.code ? ` (${location.code})` : ""}</option>)}</Select></div> : null}
          {form.mapping_method === "reporting_tag" ? <><div><Label htmlFor="tag">{t("reportingTag")}</Label><Select id="tag" value={tagId} required onChange={(e) => { setTagId(e.target.value); setForm({ ...form, zoho_value: "", zoho_label: "" }); }}><option value="">—</option>{tags.map((tag) => <option key={tag.zoho_tag_id} value={tag.zoho_tag_id}>{tag.name}</option>)}</Select></div><div><Label htmlFor="tag_option">{t("tagOption")}</Label><Select id="tag_option" value={form.zoho_value} required onChange={(e) => selectTagOption(e.target.value)}><option value="">—</option>{selectedTag?.options.map((option) => <option key={option.zoho_tag_option_id} value={option.zoho_tag_option_id}>{option.name}</option>)}</Select></div></> : null}
          {showAdvanced ? <details className="rounded-md border border-border p-3"><summary className="cursor-pointer text-sm font-medium">{t("advanced")}</summary><div className="mt-3"><Label htmlFor="zoho_value">{t("zohoValue")}</Label><Input id="zoho_value" value={form.zoho_value} required onChange={(e) => setForm({ ...form, zoho_value: e.target.value })}/><FieldError>{fieldErrors.zoho_value?.[0]}</FieldError></div></details> : null}
          {!showAdvanced && !["zoho_location", "reporting_tag"].includes(form.mapping_method) ? <Alert>{t("advancedRequired")}</Alert> : null}
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={Boolean(form.is_active)} onChange={(e) => setForm({ ...form, is_active: e.target.checked })}/>{t("isActive")}</label>
          <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>{tCommon("cancel")}</Button><Button type="submit" disabled={busy || !form.zoho_value}>{tCommon("save")}</Button></div>
        </form>
      </Modal>

      <ConfirmDialog open={applyOpen} title={t("applyAutoMatch")} description={t("applyConfirm")} confirmLabel={tCommon("apply")} loading={busy} onCancel={() => setApplyOpen(false)} onConfirm={() => { setApplyOpen(false); void run(applyZohoAutoMatch, t("applySuccess")); }} />
      <ConfirmDialog open={Boolean(deleteTarget)} title={tCommon("delete")} description={t("deleteConfirm")} confirmLabel={tCommon("delete")} danger loading={busy} onCancel={() => setDeleteTarget(null)} onConfirm={() => { if (!deleteTarget) return; const id = deleteTarget.id; setDeleteTarget(null); void run(() => deleteZohoBranchMapping(id), t("deleteSuccess")); }} />
    </div>
  );
}
