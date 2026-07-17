"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { ApiError, apiFetch } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input, Label, Select, TextArea } from "@/components/ui/form";
import { Alert, EmptyState, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

type ConflictRow = {
  id: number;
  conflict_type: string;
  status: string;
  customer_number?: string | null;
  explanation?: string | null;
  deferred?: boolean;
  customer?: { id: number; contact_name?: string | null; customer_number?: string | null };
};

type ReviewPayload = {
  customer: {
    id: number;
    contact_name?: string;
    customer_number?: string | null;
    extracted_customer_number?: string | null;
    clean_display_name?: string | null;
    branch?: { name_en?: string; code?: string } | null;
  };
  detected_prefix?: string | null;
  recommended_action?: string;
  risk_level?: string;
  protected_history?: boolean;
  counts?: Record<string, number>;
  invoice_location_evidence?: { primary_branch_id?: number | null };
  permanent_collector?: { name?: string } | null;
};

type Report = {
  total_customers: number;
  prefix_mapped: number;
  location_mapped: number;
  manually_overridden: number;
  unmapped: number;
  conflicted: number;
  protected_history: number;
  missing_customer_numbers: number;
  unknown_prefixes: number;
  headquarter: number;
  test_ignored: number;
  unmapped_by_class?: Record<string, number>;
};

const ACTIONS = [
  "keep_current_branch",
  "apply_prefix_branch",
  "apply_invoice_location_branch",
  "select_branch_manually",
  "add_administrator_override",
  "mark_headquarter",
  "mark_non_operational",
  "ignore_temporarily",
  "correct_customer_number",
];

export default function MappingCleanupPage() {
  const locale = useLocale();
  const fa = locale === "fa";
  const [tab, setTab] = useState<"conflicts" | "backfill" | "unmapped" | "metrics">("conflicts");
  const [conflicts, setConflicts] = useState<ConflictRow[]>([]);
  const [selected, setSelected] = useState<number | null>(null);
  const [review, setReview] = useState<ReviewPayload | null>(null);
  const [action, setAction] = useState("keep_current_branch");
  const [reason, setReason] = useState("");
  const [branchId, setBranchId] = useState("");
  const [customerNumber, setCustomerNumber] = useState("");
  const [report, setReport] = useState<Report | null>(null);
  const [unmapped, setUnmapped] = useState<Array<{ id: number; contact_name?: string; unmapped_classification?: string }>>([]);
  const [output, setOutput] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [c, r, u] = await Promise.all([
        apiFetch<ConflictRow[]>("/customer-prefix-mappings/conflicts?status=open&per_page=50"),
        apiFetch<Report>("/customer-prefix-mappings/report"),
        apiFetch<Array<{ id: number; contact_name?: string; unmapped_classification?: string }>>(
          "/customer-prefix-mappings/unmapped?per_page=50",
        ),
      ]);
      setConflicts(c.data);
      setReport(r.data);
      setUnmapped(u.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function openReview(id: number) {
    setBusy(true);
    setSelected(id);
    try {
      const res = await apiFetch<ReviewPayload>(`/customer-prefix-mappings/conflicts/${id}`);
      setReview(res.data);
      setAction(res.data.recommended_action || "keep_current_branch");
      setCustomerNumber(res.data.customer.extracted_customer_number || "");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function resolve() {
    if (!selected) return;
    setBusy(true);
    try {
      await apiFetch(`/customer-prefix-mappings/conflicts/${selected}/resolve`, {
        method: "POST",
        body: JSON.stringify({
          action,
          reason,
          branch_id: branchId ? Number(branchId) : undefined,
          customer_number: customerNumber || undefined,
        }),
      });
      setReason("");
      setReview(null);
      setSelected(null);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function runBackfill(apply: boolean) {
    setBusy(true);
    setOutput(null);
    try {
      const path = apply ? "/customer-prefix-mappings/backfill-apply" : "/customer-prefix-mappings/backfill-dry-run";
      const body = apply ? { confirm: true, only_missing: true } : { only_missing: true };
      const res = await apiFetch<{ output: string }>(path, { method: "POST", body: JSON.stringify(body) });
      setOutput(res.data.output);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function classifyAll() {
    setBusy(true);
    try {
      await apiFetch("/customer-prefix-mappings/classify-unmapped", {
        method: "POST",
        body: JSON.stringify({ limit: 500 }),
      });
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
        title={fa ? "پاکسازی نگاشت مشتری (۵.۳)" : "Customer mapping cleanup (5.3)"}
        subtitle={
          fa
            ? "بررسی تعارض‌ها، تکمیل شماره مشتری، و طبقه‌بندی بدون نگاشت"
            : "Review conflicts, backfill customer numbers, classify unmapped"
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {(
          [
            ["conflicts", fa ? "تعارض‌ها" : "Conflicts"],
            ["backfill", fa ? "تکمیل شماره" : "Backfill"],
            ["unmapped", fa ? "بدون نگاشت" : "Unmapped"],
            ["metrics", fa ? "شاخص‌ها" : "Metrics"],
          ] as const
        ).map(([key, label]) => (
          <Button key={key} variant={tab === key ? "primary" : "secondary"} onClick={() => setTab(key)}>
            {label}
          </Button>
        ))}
      </div>

      {loading ? <LoadingState label={fa ? "بارگذاری…" : "Loading…"} /> : null}

      {tab === "conflicts" ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <Panel className="p-4">
            {conflicts.length === 0 ? (
              <EmptyState label={fa ? "تعارض بازی نیست" : "No open conflicts"} />
            ) : (
              <ul className="divide-y divide-border text-sm">
                {conflicts.map((c) => (
                  <li key={c.id} className="flex items-center justify-between gap-2 py-3">
                    <div>
                      <p className="font-medium">
                        {c.customer?.contact_name || c.customer_number || c.id}
                      </p>
                      <p className="text-muted">{c.conflict_type}</p>
                    </div>
                    <Button size="sm" variant="secondary" disabled={busy} onClick={() => void openReview(c.id)}>
                      {fa ? "بررسی" : "Review"}
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
          <Panel className="space-y-3 p-4">
            {!review ? (
              <EmptyState label={fa ? "یک تعارض را انتخاب کنید" : "Select a conflict"} />
            ) : (
              <>
                <p className="text-sm font-medium">{review.customer.contact_name}</p>
                <p className="text-xs text-muted">
                  {fa ? "پیشوند" : "Prefix"}: {review.detected_prefix || "—"} ·{" "}
                  {fa ? "استخراج" : "Extracted"}: {review.customer.extracted_customer_number || "—"} ·{" "}
                  {fa ? "ریسک" : "Risk"}: {review.risk_level}
                  {review.protected_history ? ` · ${fa ? "محافظت‌شده" : "protected"}` : ""}
                </p>
                <p className="text-xs text-muted">
                  payments {review.counts?.payments ?? 0} · receipts {review.counts?.receipts ?? 0} · handovers{" "}
                  {review.counts?.handovers ?? 0} · promises {review.counts?.promises ?? 0}
                </p>
                <div>
                  <Label>{fa ? "اقدام" : "Action"}</Label>
                  <Select value={action} onChange={(e) => setAction(e.target.value)}>
                    {ACTIONS.map((a) => (
                      <option key={a} value={a}>
                        {a}
                      </option>
                    ))}
                  </Select>
                </div>
                <div>
                  <Label>{fa ? "شعبه (در صورت نیاز)" : "Branch id (if needed)"}</Label>
                  <Input value={branchId} onChange={(e) => setBranchId(e.target.value)} />
                </div>
                <div>
                  <Label>{fa ? "شماره مشتری" : "Customer number"}</Label>
                  <Input value={customerNumber} onChange={(e) => setCustomerNumber(e.target.value)} />
                </div>
                <div>
                  <Label>{fa ? "دلیل (الزامی)" : "Reason (required)"}</Label>
                  <TextArea value={reason} onChange={(e) => setReason(e.target.value)} />
                </div>
                <Button disabled={busy || reason.trim().length < 3} onClick={() => void resolve()}>
                  {fa ? "ثبت تصمیم" : "Resolve"}
                </Button>
              </>
            )}
          </Panel>
        </div>
      ) : null}

      {tab === "backfill" ? (
        <Panel className="space-y-3 p-4">
          <p className="text-sm text-muted">
            {fa
              ? "شماره مشتری را از ابتدای نام مخاطب با پیشوندهای پیکربندی‌شده استخراج می‌کند. پیش‌فرض dry-run است."
              : "Extracts customer numbers from contact-name starts using configured prefixes. Dry-run by default."}
          </p>
          <div className="flex flex-wrap gap-2">
            <Button disabled={busy} variant="secondary" onClick={() => void runBackfill(false)}>
              {fa ? "dry-run" : "Dry-run"}
            </Button>
            <Button disabled={busy} onClick={() => void runBackfill(true)}>
              {fa ? "اعمال (--apply)" : "Apply (--apply)"}
            </Button>
          </div>
          {output ? <pre className="max-h-64 overflow-auto rounded bg-sand-soft/40 p-2 text-xs">{output}</pre> : null}
        </Panel>
      ) : null}

      {tab === "unmapped" ? (
        <Panel className="space-y-3 p-4">
          <Button disabled={busy} onClick={() => void classifyAll()}>
            {fa ? "طبقه‌بندی دسته‌ای" : "Classify unmapped"}
          </Button>
          {unmapped.length === 0 ? (
            <EmptyState label={fa ? "بدون نگاشتی نیست" : "No unmapped rows"} />
          ) : (
            <ul className="divide-y divide-border text-sm">
              {unmapped.map((u) => (
                <li key={u.id} className="py-2">
                  {u.contact_name || u.id} · {u.unmapped_classification || (fa ? "طبقه‌بندی نشده" : "unclassified")}
                </li>
              ))}
            </ul>
          )}
        </Panel>
      ) : null}

      {tab === "metrics" && report ? (
        <Panel className="space-y-2 p-4 text-sm">
          {(
            [
              ["total_customers", fa ? "کل مشتریان" : "Total"],
              ["prefix_mapped", fa ? "نگاشت پیشوند" : "Prefix-mapped"],
              ["location_mapped", fa ? "نگاشت موقعیت" : "Location-mapped"],
              ["manually_overridden", fa ? "لغو ادمین" : "Manual override"],
              ["unmapped", fa ? "بدون نگاشت" : "Unmapped"],
              ["conflicted", fa ? "تعارض" : "Conflicted"],
              ["protected_history", fa ? "محافظت‌شده" : "Protected"],
              ["missing_customer_numbers", fa ? "بدون شماره" : "Missing number"],
              ["unknown_prefixes", fa ? "پیشوند ناشناخته" : "Unknown prefix"],
              ["headquarter", "Headquarter"],
              ["test_ignored", fa ? "تست/نادیده" : "Test/ignored"],
            ] as const
          ).map(([key, label]) => (
            <div key={key} className="flex justify-between border-b border-border py-2">
              <span>{label}</span>
              <span>{report[key]}</span>
            </div>
          ))}
        </Panel>
      ) : null}
    </div>
  );
}
