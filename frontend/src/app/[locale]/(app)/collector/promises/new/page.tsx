"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { createPromise } from "@/lib/promises";
import { Button } from "@/components/ui/button";
import { Input, Label, TextArea } from "@/components/ui/form";
import { Alert, PageHeader, Panel } from "@/components/ui/layout";

export default function NewPromisePage() {
  const t = useTranslations("promisesPage");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const search = useSearchParams();

  const [customerId, setCustomerId] = useState(
    search.get("customer_id") || "",
  );
  const [assignmentId] = useState(search.get("assignment_id") || "");
  const [amount, setAmount] = useState("");
  const [currency, setCurrency] = useState("AFN");
  const [promisedDate, setPromisedDate] = useState("");
  const [notes, setNotes] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      await createPromise({
        customer_id: Number(customerId),
        assignment_id: assignmentId ? Number(assignmentId) : undefined,
        amount,
        currency: currency.trim() || undefined,
        promised_date: promisedDate,
        notes: notes.trim() || undefined,
      });
      router.push("/collector");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={t("createTitle")} subtitle={t("subtitle")} />
      {error ? <Alert>{error}</Alert> : null}

      <Panel className="p-4">
        <form onSubmit={onSubmit} className="space-y-4">
          <div>
            <Label>{t("customerId")}</Label>
            <Input
              type="number"
              value={customerId}
              onChange={(e) => setCustomerId(e.target.value)}
              required
              className="h-12"
            />
          </div>
          <div>
            <Label>{t("amount")}</Label>
            <Input
              type="number"
              step="0.01"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
              className="h-12"
            />
          </div>
          <div>
            <Label>{t("currency")}</Label>
            <Input
              value={currency}
              onChange={(e) => setCurrency(e.target.value)}
              maxLength={3}
              className="h-12"
            />
          </div>
          <div>
            <Label>{t("date")}</Label>
            <Input
              type="date"
              value={promisedDate}
              onChange={(e) => setPromisedDate(e.target.value)}
              required
              className="h-12"
            />
          </div>
          <div>
            <Label>{t("notes")}</Label>
            <TextArea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
          </div>
          <Button type="submit" className="h-12 w-full" disabled={saving}>
            {tCommon("create")}
          </Button>
        </form>
      </Panel>
    </div>
  );
}
