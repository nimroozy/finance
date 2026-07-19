"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { createAssignment } from "@/lib/assignments";
import { listCollectors } from "@/lib/collectors";
import type { Collector } from "@/lib/types";
import { Button } from "@/components/ui/button";
import { FieldError, Input, Label, Select, TextArea } from "@/components/ui/form";
import { CustomerPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";
import {
  Alert,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function NewAssignmentPage() {
  const t = useTranslations("assignments");
  const tCommon = useTranslations("common");
  const router = useRouter();

  const [collectors, setCollectors] = useState<Collector[]>([]);
  const [customer, setCustomer] = useState<SearchableOption | null>(null);
  const [collectorId, setCollectorId] = useState("");
  const [priority, setPriority] = useState("normal");
  const [dueDate, setDueDate] = useState("");
  const [notes, setNotes] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [warnings, setWarnings] = useState<string[]>([]);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    void listCollectors({ is_active: true })
      .then((res) => setCollectors(res.data))
      .catch(() => setCollectors([]));
  }, []);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setFieldErrors({});
    setWarnings([]);
    try {
      const res = await createAssignment({
        customer_id: Number(customer?.id),
        collector_id: Number(collectorId),
        priority,
        due_date: dueDate || undefined,
        notes: notes.trim() || undefined,
      });
      if (res.data.warnings?.length) setWarnings(res.data.warnings);
      router.push(`/assignments/${res.data.assignment.id}`);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError(tCommon("error"));
      }
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
      {warnings.length > 0 ? (
        <div className="mb-4">
          <Alert tone="warning">
            {t("warnings")}: {warnings.join(" ")}
          </Alert>
        </div>
      ) : null}

      <Panel className="p-4 sm:p-6">
        <form onSubmit={onSubmit} className="mx-auto max-w-lg space-y-4">
          <div>
            <Label>{t("selectCustomer")}</Label>
            <CustomerPicker value={customer} onChange={setCustomer} />
            <FieldError>{fieldErrors.customer_id?.[0]}</FieldError>
          </div>
          <div>
            <Label htmlFor="collector">{t("collector")}</Label>
            <Select
              id="collector"
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
            <FieldError>{fieldErrors.collector_id?.[0]}</FieldError>
          </div>
          <div>
            <Label htmlFor="priority">{t("priority")}</Label>
            <Select
              id="priority"
              value={priority}
              onChange={(e) => setPriority(e.target.value)}
            >
              {(["low", "normal", "high", "urgent"] as const).map((p) => (
                <option key={p} value={p}>
                  {t(`priorities.${p}`)}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="dueDate">{t("dueDate")}</Label>
            <Input
              id="dueDate"
              type="date"
              value={dueDate}
              onChange={(e) => setDueDate(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="notes">{t("notes")}</Label>
            <TextArea
              id="notes"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
          </div>
          <div className="flex gap-2">
            <Button type="submit" disabled={saving || !customer}>
              {tCommon("create")}
            </Button>
            <Button
              type="button"
              variant="secondary"
              onClick={() => router.push("/assignments")}
            >
              {tCommon("cancel")}
            </Button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
