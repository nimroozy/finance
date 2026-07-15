"use client";

import { useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { uploadVisitFile } from "@/lib/evidence";
import { getCurrentPosition } from "@/lib/geolocation";
import { createVisit, listVisitOutcomes } from "@/lib/visits";
import type { VisitOutcome } from "@/lib/types";
import { Button } from "@/components/ui/button";
import { Input, Label, Select, TextArea } from "@/components/ui/form";
import { Alert, PageHeader, Panel } from "@/components/ui/layout";

export default function NewVisitPage() {
  const t = useTranslations("visitsPage");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const search = useSearchParams();

  const assignmentId = search.get("assignment_id") || "";
  const customerIdParam = search.get("customer_id") || "";

  const [outcomes, setOutcomes] = useState<VisitOutcome[]>([]);
  const [customerId, setCustomerId] = useState(customerIdParam);
  const [outcome, setOutcome] = useState("");
  const [notes, setNotes] = useState("");
  const [followUp, setFollowUp] = useState(false);
  const [followUpDate, setFollowUpDate] = useState("");
  const [includePromise, setIncludePromise] = useState(false);
  const [promiseAmount, setPromiseAmount] = useState("");
  const [promiseDate, setPromiseDate] = useState("");
  const [lat, setLat] = useState<number | null>(null);
  const [lng, setLng] = useState<number | null>(null);
  const [gpsMsg, setGpsMsg] = useState<string | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listVisitOutcomes()
      .then((res) => {
        setOutcomes(res.data);
        if (res.data[0]) setOutcome(res.data[0].code);
      })
      .catch(() => setOutcomes([]));
  }, []);

  useEffect(() => {
    if (outcome === "promise_to_pay") setIncludePromise(true);
    if (outcome === "follow_up") setFollowUp(true);
  }, [outcome]);

  const needsCustomer = useMemo(() => !assignmentId, [assignmentId]);

  async function captureGps() {
    const pos = await getCurrentPosition();
    if (pos.permission === "granted") {
      setLat(pos.lat);
      setLng(pos.lng);
      setGpsMsg(t("gpsOk"));
    } else if (pos.permission === "denied") {
      setGpsMsg(t("gpsDenied"));
    } else {
      setGpsMsg(t("gpsUnavailable"));
    }
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const res = await createVisit({
        assignment_id: assignmentId ? Number(assignmentId) : undefined,
        customer_id: customerId ? Number(customerId) : undefined,
        outcome,
        notes: notes.trim() || undefined,
        follow_up_required: followUp,
        follow_up_date: followUp && followUpDate ? followUpDate : undefined,
        latitude: lat ?? undefined,
        longitude: lng ?? undefined,
        promise:
          includePromise && promiseAmount && promiseDate
            ? {
                amount: promiseAmount,
                promised_date: promiseDate,
              }
            : undefined,
      });
      if (file) {
        try {
          await uploadVisitFile(res.data.id, file);
        } catch {
          // visit created; photo optional
        }
      }
      router.push(`/collector/visits`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={t("createTitle")} />
      {error ? <Alert>{error}</Alert> : null}
      {gpsMsg ? <Alert tone="info">{gpsMsg}</Alert> : null}

      <Panel className="p-4">
        <form onSubmit={onSubmit} className="space-y-4">
          {needsCustomer || customerIdParam ? (
            <div>
              <Label>Customer ID</Label>
              <Input
                type="number"
                value={customerId}
                onChange={(e) => setCustomerId(e.target.value)}
                required={!assignmentId}
              />
            </div>
          ) : null}

          <div>
            <Label>{t("outcome")}</Label>
            <Select
              value={outcome}
              onChange={(e) => setOutcome(e.target.value)}
              required
              className="h-12"
            >
              {outcomes.map((o) => (
                <option key={o.code} value={o.code}>
                  {locale === "fa" ? o.label_fa : o.label_en}
                </option>
              ))}
            </Select>
          </div>

          <div>
            <Label>{t("notes")}</Label>
            <TextArea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="min-h-28"
            />
          </div>

          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={followUp}
              onChange={(e) => setFollowUp(e.target.checked)}
            />
            {t("followUp")}
          </label>
          {followUp ? (
            <div>
              <Label>{t("followUpDate")}</Label>
              <Input
                type="date"
                value={followUpDate}
                onChange={(e) => setFollowUpDate(e.target.value)}
                className="h-12"
              />
            </div>
          ) : null}

          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={includePromise}
              onChange={(e) => setIncludePromise(e.target.checked)}
            />
            {t("includePromise")}
          </label>
          {includePromise ? (
            <div className="grid gap-3">
              <div>
                <Label>{t("promiseAmount")}</Label>
                <Input
                  type="number"
                  step="0.01"
                  value={promiseAmount}
                  onChange={(e) => setPromiseAmount(e.target.value)}
                  required={includePromise}
                  className="h-12"
                />
              </div>
              <div>
                <Label>{t("promiseDate")}</Label>
                <Input
                  type="date"
                  value={promiseDate}
                  onChange={(e) => setPromiseDate(e.target.value)}
                  required={includePromise}
                  className="h-12"
                />
              </div>
            </div>
          ) : null}

          <Button
            type="button"
            variant="secondary"
            className="h-12 w-full"
            onClick={() => void captureGps()}
          >
            {t("captureGps")}
            {lat != null && lng != null
              ? ` (${lat.toFixed(5)}, ${lng.toFixed(5)})`
              : ""}
          </Button>

          <div>
            <Label>{t("uploadPhoto")}</Label>
            <Input
              type="file"
              accept="image/*,application/pdf"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              className="h-12 py-2"
            />
          </div>

          <Button type="submit" className="h-12 w-full" disabled={saving}>
            {tCommon("save")}
          </Button>
        </form>
      </Panel>
    </div>
  );
}
