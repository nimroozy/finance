"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { listBranches } from "@/lib/auth";
import { listCollectors } from "@/lib/collectors";
import { createRoute } from "@/lib/routes";
import type { Branch, Collector } from "@/lib/types";
import { Button } from "@/components/ui/button";
import { Input, Label, Select, TextArea } from "@/components/ui/form";
import { Alert, PageHeader, Panel } from "@/components/ui/layout";

type StopDraft = { customer_id: string; notes: string };

export default function NewRoutePage() {
  const t = useTranslations("routesPage");
  const tCommon = useTranslations("common");
  const router = useRouter();

  const [branches, setBranches] = useState<Branch[]>([]);
  const [collectors, setCollectors] = useState<Collector[]>([]);
  const [branchId, setBranchId] = useState("");
  const [collectorId, setCollectorId] = useState("");
  const [name, setName] = useState("");
  const [routeDate, setRouteDate] = useState("");
  const [notes, setNotes] = useState("");
  const [stops, setStops] = useState<StopDraft[]>([
    { customer_id: "", notes: "" },
  ]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

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

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const res = await createRoute({
        branch_id: Number(branchId),
        collector_id: Number(collectorId),
        name: name.trim(),
        route_date: routeDate,
        notes: notes.trim() || undefined,
        stops: stops
          .filter((s) => s.customer_id)
          .map((s, i) => ({
            customer_id: Number(s.customer_id),
            sequence: i + 1,
            notes: s.notes.trim() || undefined,
          })),
      });
      router.push(`/routes/${res.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader title={t("create")} subtitle={t("subtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Panel className="p-4 sm:p-6">
        <form onSubmit={onSubmit} className="mx-auto max-w-xl space-y-4">
          <div>
            <Label>{t("name")}</Label>
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
            />
          </div>
          <div>
            <Label>{t("date")}</Label>
            <Input
              type="date"
              value={routeDate}
              onChange={(e) => setRouteDate(e.target.value)}
              required
            />
          </div>
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
            <Label>{t("collector")}</Label>
            <Select
              value={collectorId}
              onChange={(e) => setCollectorId(e.target.value)}
              required
            >
              <option value="">—</option>
              {collectors.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.user?.name ?? `#${c.id}`}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label>{t("notes")}</Label>
            <TextArea value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <Label className="mb-0">{t("stops")}</Label>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                onClick={() =>
                  setStops((prev) => [...prev, { customer_id: "", notes: "" }])
                }
              >
                {t("addStop")}
              </Button>
            </div>
            {stops.map((stop, idx) => (
              <div
                key={idx}
                className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-2"
              >
                <div>
                  <Label>{t("customerId")}</Label>
                  <Input
                    type="number"
                    value={stop.customer_id}
                    onChange={(e) =>
                      setStops((prev) =>
                        prev.map((s, i) =>
                          i === idx
                            ? { ...s, customer_id: e.target.value }
                            : s,
                        ),
                      )
                    }
                  />
                </div>
                <div>
                  <Label>{t("notes")}</Label>
                  <Input
                    value={stop.notes}
                    onChange={(e) =>
                      setStops((prev) =>
                        prev.map((s, i) =>
                          i === idx ? { ...s, notes: e.target.value } : s,
                        ),
                      )
                    }
                  />
                </div>
              </div>
            ))}
          </div>

          <Button type="submit" disabled={saving}>
            {tCommon("create")}
          </Button>
        </form>
      </Panel>
    </div>
  );
}
