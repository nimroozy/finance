"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { getSettings, updateSettings } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { FieldError, Input, Label } from "@/components/ui/form";
import {
  Alert,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function SettingsPage() {
  const t = useTranslations("settings");
  const tCommon = useTranslations("common");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [form, setForm] = useState({
    company_name: "",
    currency: "",
    timezone: "",
  });

  useEffect(() => {
    let cancelled = false;
    async function load() {
      setLoading(true);
      try {
        const res = await getSettings();
        if (!cancelled) {
          setForm({
            company_name: String(res.data.company_name ?? ""),
            currency: String(res.data.currency ?? ""),
            timezone: String(res.data.timezone ?? ""),
          });
        }
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : tCommon("error"));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, [tCommon]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    setFieldErrors({});
    try {
      await updateSettings(form);
      setSuccess(t("saveSuccess"));
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
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel className="p-5">
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : (
          <form onSubmit={onSubmit} className="mx-auto max-w-lg space-y-4">
            <div>
              <Label htmlFor="company_name">{t("companyName")}</Label>
              <Input
                id="company_name"
                value={form.company_name}
                onChange={(e) =>
                  setForm({ ...form, company_name: e.target.value })
                }
                required
              />
              <FieldError>{fieldErrors.company_name?.[0]}</FieldError>
            </div>
            <div>
              <Label htmlFor="currency">{t("currency")}</Label>
              <Input
                id="currency"
                value={form.currency}
                onChange={(e) =>
                  setForm({ ...form, currency: e.target.value })
                }
                required
              />
              <FieldError>{fieldErrors.currency?.[0]}</FieldError>
            </div>
            <div>
              <Label htmlFor="timezone">{t("timezone")}</Label>
              <Input
                id="timezone"
                value={form.timezone}
                onChange={(e) =>
                  setForm({ ...form, timezone: e.target.value })
                }
                required
                placeholder="Asia/Kabul"
              />
              <FieldError>{fieldErrors.timezone?.[0]}</FieldError>
            </div>
            <div className="pt-2">
              <Button type="submit" disabled={saving}>
                {tCommon("save")}
              </Button>
            </div>
          </form>
        )}
      </Panel>
    </div>
  );
}
