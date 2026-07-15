"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  autoAssignConfirm,
  autoAssignPreview,
  bulkAssign,
} from "@/lib/assignments";
import { listCollectors } from "@/lib/collectors";
import { listBranches } from "@/lib/auth";
import type {
  AssignmentBulkOperation,
  Branch,
  Collector,
} from "@/lib/types";
import { Button } from "@/components/ui/button";
import { Input, Label, Select, TextArea } from "@/components/ui/form";
import {
  Alert,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function BulkAssignPage() {
  const t = useTranslations("assignments");
  const tCommon = useTranslations("common");

  const [tab, setTab] = useState<"bulk" | "auto">("bulk");
  const [branches, setBranches] = useState<Branch[]>([]);
  const [collectors, setCollectors] = useState<Collector[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [customerIds, setCustomerIds] = useState("");
  const [collectorId, setCollectorId] = useState("");
  const [branchId, setBranchId] = useState("");
  const [notes, setNotes] = useState("");

  const [strategy, setStrategy] = useState<"least_loaded" | "round_robin">(
    "least_loaded",
  );
  const [preview, setPreview] = useState<AssignmentBulkOperation | null>(null);

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

  function parseIds(raw: string) {
    return raw
      .split(/[\s,]+/)
      .map((x) => Number(x.trim()))
      .filter((n) => Number.isFinite(n) && n > 0);
  }

  async function onBulk(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      await bulkAssign({
        customer_ids: parseIds(customerIds),
        collector_id: Number(collectorId),
        branch_id: branchId ? Number(branchId) : undefined,
        notes: notes.trim() || undefined,
      });
      setSuccess(t("bulkSuccess"));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  async function onPreview(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      const ids = parseIds(customerIds);
      const res = await autoAssignPreview({
        branch_id: Number(branchId),
        strategy,
        customer_ids: ids.length ? ids : undefined,
      });
      setPreview(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  async function onConfirm() {
    if (!preview) return;
    setSaving(true);
    setError(null);
    try {
      await autoAssignConfirm(preview.id);
      setSuccess(t("bulkSuccess"));
      setPreview(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader title={t("bulk")} subtitle={t("subtitle")} />

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

      <div className="mb-4 flex gap-2">
        <Button
          variant={tab === "bulk" ? "primary" : "secondary"}
          onClick={() => setTab("bulk")}
        >
          {t("assignSelected")}
        </Button>
        <Button
          variant={tab === "auto" ? "primary" : "secondary"}
          onClick={() => setTab("auto")}
        >
          {t("autoPreview")}
        </Button>
      </div>

      <Panel className="p-4 sm:p-6">
        {tab === "bulk" ? (
          <form onSubmit={onBulk} className="mx-auto max-w-lg space-y-4">
            <div>
              <Label>{t("selectCustomers")}</Label>
              <TextArea
                value={customerIds}
                onChange={(e) => setCustomerIds(e.target.value)}
                placeholder="1, 2, 3"
                required
              />
            </div>
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
              <Label>{t("branch")}</Label>
              <Select
                value={branchId}
                onChange={(e) => setBranchId(e.target.value)}
              >
                <option value="">—</option>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.code}
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
            <Button type="submit" disabled={saving}>
              {tCommon("confirm")}
            </Button>
          </form>
        ) : (
          <form onSubmit={onPreview} className="mx-auto max-w-lg space-y-4">
            <div>
              <Label>{t("branch")}</Label>
              <Select
                value={branchId}
                onChange={(e) => setBranchId(e.target.value)}
                required
              >
                <option value="">—</option>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.code}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>{t("strategy")}</Label>
              <Select
                value={strategy}
                onChange={(e) =>
                  setStrategy(e.target.value as "least_loaded" | "round_robin")
                }
              >
                <option value="least_loaded">{t("leastLoaded")}</option>
                <option value="round_robin">{t("roundRobin")}</option>
              </Select>
            </div>
            <div>
              <Label>{t("selectCustomers")}</Label>
              <Input
                value={customerIds}
                onChange={(e) => setCustomerIds(e.target.value)}
                placeholder="optional: 1, 2, 3"
              />
            </div>
            <Button type="submit" disabled={saving}>
              {t("autoPreview")}
            </Button>

            {preview ? (
              <div className="rounded-md border border-border p-3 text-sm">
                <p className="font-medium">{t("previewResult")}</p>
                <p>
                  #{preview.id} · {preview.strategy} · {preview.status} ·{" "}
                  {preview.selected_count ?? 0}
                </p>
                <pre className="mt-2 max-h-48 overflow-auto text-xs">
                  {JSON.stringify(preview.preview_payload, null, 2)}
                </pre>
                <Button
                  className="mt-3"
                  type="button"
                  disabled={saving}
                  onClick={() => void onConfirm()}
                >
                  {t("autoConfirm")}
                </Button>
              </div>
            ) : null}
          </form>
        )}
      </Panel>
    </div>
  );
}
