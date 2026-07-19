"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { createPromise } from "@/lib/promises";
import { getCustomer } from "@/lib/customers";
import { Button } from "@/components/ui/button";
import { Input, Label, TextArea } from "@/components/ui/form";
import { Alert, PageHeader, Panel } from "@/components/ui/layout";
import { CustomerPicker } from "@/components/ui/pickers";
import type { SearchableOption } from "@/components/ui/searchable-select";

export default function NewPromisePage() {
  const t = useTranslations("promisesPage");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const search = useSearchParams();

  const customerIdParam = search.get("customer_id") || "";
  const [customer, setCustomer] = useState<SearchableOption | null>(null);
  const [assignmentId] = useState(search.get("assignment_id") || "");
  const [amount, setAmount] = useState("");
  const [currency, setCurrency] = useState("AFN");
  const [promisedDate, setPromisedDate] = useState("");
  const [notes, setNotes] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!customerIdParam) return;
    void getCustomer(Number(customerIdParam))
      .then((res) =>
        setCustomer({
          id: res.data.id,
          label: res.data.contact_name || res.data.company_name || `#${res.data.id}`,
          description: res.data.customer_number ?? undefined,
        }),
      )
      .catch(() => undefined);
  }, [customerIdParam]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!customer) return;
    setSaving(true);
    setError(null);
    try {
      await createPromise({
        customer_id: Number(customer.id),
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
            <Label>{t("customer")}</Label>
            <CustomerPicker value={customer} onChange={setCustomer} />
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
          <Button type="submit" className="h-12 w-full" disabled={saving || !customer}>
            {tCommon("create")}
          </Button>
        </form>
      </Panel>
    </div>
  );
}
