"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { ApiError, apiFetch } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input, Label, Select, TextArea } from "@/components/ui/form";
import { Alert, EmptyState, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

type PrefixRow = {
  id: number;
  branch_id: number;
  prefix: string;
  normalized_prefix: string;
  active: boolean;
  priority: number;
  description?: string | null;
  matched_customers_estimate?: number;
  branch?: { id: number; code: string; name_en?: string | null };
  examples?: string[];
};

type BranchOption = { id: number; code: string; name_en?: string | null; name_fa?: string | null };
type ConflictRow = {
  id: number;
  conflict_type: string;
  status: string;
  customer_number?: string | null;
  explanation?: string | null;
  customer?: { id: number; contact_name?: string | null };
};
type ReportSummary = {
  by_branch: Array<{ code: string; name_en?: string; prefix_customers: number; total_customers: number; total_debt: number | string }>;
  unknown_prefixes: number;
  missing_customer_numbers: number;
  admin_overrides: number;
  protected_history_pending: number;
  open_conflicts: number;
};

export default function CustomerPrefixMappingsPage() {
  const locale = useLocale();
  const fa = locale === "fa";
  const [tab, setTab] = useState<"prefixes" | "conflicts" | "report">("prefixes");
  const [rows, setRows] = useState<PrefixRow[]>([]);
  const [branches, setBranches] = useState<BranchOption[]>([]);
  const [conflicts, setConflicts] = useState<ConflictRow[]>([]);
  const [report, setReport] = useState<ReportSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [prefix, setPrefix] = useState("");
  const [branchId, setBranchId] = useState("");
  const [priority, setPriority] = useState("100");
  const [description, setDescription] = useState("");
  const [testNumber, setTestNumber] = useState("");
  const [testResult, setTestResult] = useState<string | null>(null);
  const [dryOutput, setDryOutput] = useState<string | null>(null);
  const [preview, setPreview] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [p, b, c, r] = await Promise.all([
        apiFetch<PrefixRow[]>("/customer-prefix-mappings"),
        apiFetch<BranchOption[]>("/branches?per_page=100"),
        apiFetch<ConflictRow[]>("/customer-prefix-mappings/conflicts?status=open&per_page=50"),
        apiFetch<ReportSummary>("/customer-prefix-mappings/report"),
      ]);
      setRows(p.data);
      setBranches(b.data);
      if (!branchId && b.data[0]) setBranchId(String(b.data[0].id));
      setConflicts(c.data);
      setReport(r.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setLoading(false);
    }
  }, [branchId]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onAdd() {
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await apiFetch("/customer-prefix-mappings", {
        method: "POST",
        body: JSON.stringify({
          branch_id: Number(branchId),
          prefix,
          priority: Number(priority) || 100,
          description: description || undefined,
          active: true,
        }),
      });
      setPrefix("");
      setDescription("");
      setMessage(fa ? "پیشوند ذخیره شد" : "Prefix saved");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function onDisable(id: number) {
    setBusy(true);
    try {
      await apiFetch(`/customer-prefix-mappings/${id}/disable`, { method: "POST", body: "{}" });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function onTest() {
    setBusy(true);
    setTestResult(null);
    try {
      const res = await apiFetch<{
        normalized: string;
        match: { prefix?: string; branch_id?: number; ambiguous?: boolean } | null;
        branch: { code?: string; name_en?: string } | null;
        unknown_prefix: boolean;
      }>("/customer-prefix-mappings/test", {
        method: "POST",
        body: JSON.stringify({ customer_number: testNumber }),
      });
      if (res.data.match && !res.data.match.ambiguous) {
        setTestResult(
          `${res.data.normalized} → ${res.data.match.prefix} / ${res.data.branch?.name_en || res.data.branch?.code || res.data.match.branch_id}`,
        );
      } else if (res.data.unknown_prefix) {
        setTestResult(fa ? "پیشوند ناشناخته" : "Unknown prefix");
      } else {
        setTestResult(fa ? "بدون تطبیق" : "No match");
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function onPreview() {
    setBusy(true);
    setPreview(null);
    try {
      const res = await apiFetch<Array<{ customer_number?: string; resolution: { resolution_source: string; conflict_type?: string | null } }>>(
        `/customer-prefix-mappings/preview?prefix=${encodeURIComponent(prefix || testNumber.slice(0, 3))}&limit=15`,
      );
      setPreview(
        res.data
          .map((r) => `${r.customer_number || "—"} → ${r.resolution.resolution_source}${r.resolution.conflict_type ? ` (${r.resolution.conflict_type})` : ""}`)
          .join("\n") || (fa ? "نتیجه‌ای نیست" : "No preview rows"),
      );
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function onDryRun() {
    setBusy(true);
    setDryOutput(null);
    try {
      const res = await apiFetch<{ output: string }>("/customer-prefix-mappings/dry-run", {
        method: "POST",
        body: "{}",
      });
      setDryOutput(res.data.output);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function onApply() {
    if (!window.confirm(fa ? "فقط نگاشت‌های unambiguous اعمال شوند؟" : "Apply only unambiguous mappings?")) return;
    setBusy(true);
    try {
      const res = await apiFetch<{ output: string }>("/customer-prefix-mappings/apply", {
        method: "POST",
        body: JSON.stringify({ confirm: true }),
      });
      setDryOutput(res.data.output);
      setMessage(fa ? "اعمال کنترل‌شده انجام شد" : "Controlled apply completed");
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={fa ? "نگاشت پیشوند شماره مشتری" : "Customer prefix mappings"}
        subtitle={
          fa
            ? "پیشوند شماره مشتری را به شعبه وصل کنید (KBL، NMZ، …)"
            : "Map customer-number prefixes to branches (KBL, NMZ, …)"
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {message ? <Alert tone="success">{message}</Alert> : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {(
          [
            ["prefixes", fa ? "پیشوندها" : "Prefixes"],
            ["conflicts", fa ? "تعارض‌ها" : "Conflicts"],
            ["report", fa ? "گزارش" : "Report"],
          ] as const
        ).map(([key, label]) => (
          <Button key={key} variant={tab === key ? "primary" : "secondary"} onClick={() => setTab(key)}>
            {label}
          </Button>
        ))}
      </div>

      {tab === "prefixes" ? (
        <>
          <div className="grid gap-4 lg:grid-cols-2">
            <Panel className="space-y-3 p-4">
              <h2 className="text-sm font-medium">{fa ? "افزودن پیشوند" : "Add prefix"}</h2>
              <div>
                <Label>{fa ? "پیشوند" : "Prefix"}</Label>
                <Input value={prefix} onChange={(e) => setPrefix(e.target.value.toUpperCase())} placeholder="KBL" />
                <p className="mt-1 text-xs text-muted">
                  {fa ? "نمونه قبل از ذخیره:" : "Examples before save:"} {(prefix || "KBL")}-001254, {(prefix || "KBL").toLowerCase()}001254, {(prefix || "KBL")}_001254
                </p>
              </div>
              <div>
                <Label>{fa ? "شعبه" : "Branch"}</Label>
                <Select value={branchId} onChange={(e) => setBranchId(e.target.value)}>
                  {branches.map((b) => (
                    <option key={b.id} value={b.id}>
                      {fa ? b.name_fa || b.name_en || b.code : b.name_en || b.code}
                    </option>
                  ))}
                </Select>
              </div>
              <div>
                <Label>{fa ? "اولویت" : "Priority"}</Label>
                <Input type="number" value={priority} onChange={(e) => setPriority(e.target.value)} />
              </div>
              <div>
                <Label>{fa ? "توضیح" : "Description"}</Label>
                <TextArea value={description} onChange={(e) => setDescription(e.target.value)} />
              </div>
              <Button disabled={busy || !prefix || !branchId} onClick={() => void onAdd()}>
                {fa ? "ذخیره" : "Save"}
              </Button>
            </Panel>

            <Panel className="space-y-3 p-4">
              <h2 className="text-sm font-medium">{fa ? "آزمایش و نگاشت" : "Test & mapping"}</h2>
              <Input value={testNumber} onChange={(e) => setTestNumber(e.target.value)} placeholder="KBL-001254" />
              <div className="flex flex-wrap gap-2">
                <Button disabled={busy || !testNumber} onClick={() => void onTest()}>
                  {fa ? "آزمایش شماره" : "Test number"}
                </Button>
                <Button variant="secondary" disabled={busy} onClick={() => void onPreview()}>
                  {fa ? "پیش‌نمایش مشتریان" : "Preview affected"}
                </Button>
                <Button variant="secondary" disabled={busy} onClick={() => void onDryRun()}>
                  {fa ? "dry-run" : "Dry-run"}
                </Button>
                <Button variant="secondary" disabled={busy} onClick={() => void onApply()}>
                  {fa ? "اعمال کنترل‌شده" : "Apply controlled"}
                </Button>
              </div>
              {testResult ? <p className="text-sm">{testResult}</p> : null}
              {preview ? <pre className="max-h-40 overflow-auto rounded bg-sand-soft/40 p-2 text-xs">{preview}</pre> : null}
              {dryOutput ? <pre className="max-h-48 overflow-auto rounded bg-sand-soft/40 p-2 text-xs">{dryOutput}</pre> : null}
            </Panel>
          </div>

          <Panel className="mt-4 p-4">
            {loading ? (
              <LoadingState label={fa ? "در حال بارگذاری…" : "Loading…"} />
            ) : rows.length === 0 ? (
              <EmptyState label={fa ? "پیشوندی نیست" : "No prefixes"} />
            ) : (
              <ul className="divide-y divide-border text-sm">
                {rows.map((row) => (
                  <li key={row.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                      <p className="font-medium">
                        {row.normalized_prefix} → {row.branch?.name_en || row.branch?.code}
                        {!row.active ? ` (${fa ? "غیرفعال" : "disabled"})` : ""}
                      </p>
                      <p className="text-muted">
                        {fa ? "اولویت" : "Priority"} {row.priority} · {fa ? "مشتریان تقریبی" : "Matched ~"}{" "}
                        {row.matched_customers_estimate ?? 0} · {row.description || "—"}
                      </p>
                    </div>
                    {row.active ? (
                      <Button variant="secondary" disabled={busy} onClick={() => void onDisable(row.id)}>
                        {fa ? "غیرفعال" : "Disable"}
                      </Button>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </>
      ) : null}

      {tab === "conflicts" ? (
        <Panel className="p-4">
          {conflicts.length === 0 ? (
            <EmptyState label={fa ? "تعارض بازی نیست" : "No open conflicts"} />
          ) : (
            <ul className="divide-y divide-border text-sm">
              {conflicts.map((c) => (
                <li key={c.id} className="py-3">
                  <p className="font-medium">
                    {c.customer_number || c.customer?.id} · {c.conflict_type}
                  </p>
                  <p className="text-muted">{c.explanation || c.customer?.contact_name || "—"}</p>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      ) : null}

      {tab === "report" && report ? (
        <Panel className="space-y-3 p-4 text-sm">
          <p>
            {fa ? "تعارض‌های باز" : "Open conflicts"}: {report.open_conflicts} · {fa ? "پیشوند ناشناخته" : "Unknown prefixes"}:{" "}
            {report.unknown_prefixes} · {fa ? "بدون شماره" : "Missing numbers"}: {report.missing_customer_numbers} ·{" "}
            {fa ? "لغو ادمین" : "Admin overrides"}: {report.admin_overrides} · {fa ? "محافظت‌شده" : "Protected pending"}:{" "}
            {report.protected_history_pending}
          </p>
          <ul className="divide-y divide-border">
            {report.by_branch.map((b) => (
              <li key={b.code} className="flex justify-between py-2">
                <span>{b.name_en || b.code}</span>
                <span>
                  {b.prefix_customers}/{b.total_customers} · debt {b.total_debt}
                </span>
              </li>
            ))}
          </ul>
        </Panel>
      ) : null}
    </div>
  );
}
