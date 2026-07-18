"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { PageHeader } from "@/components/ui/layout";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/form";
import { BranchPicker } from "@/components/ui/pickers";
import { StatusBadge } from "@/components/status-badge";
import { ErrorState } from "@/components/ui/error-state";
import { ValidationSummary } from "@/components/ui/validation-summary";
import { useToast } from "@/components/ui/toast";
import type { SearchableOption } from "@/components/ui/searchable-select";
import {
  listBranchPaymentMappings,
  listZohoAccounts,
  listZohoPaymentModes,
  upsertBranchPaymentMapping,
  validateBranchPaymentMapping,
  type BranchPaymentMapping,
  type ZohoAccountOption,
  type ZohoPaymentModeOption,
} from "@/lib/ownership";
import { ApiError } from "@/lib/api";

export default function BranchPaymentMappingsPage() {
  const t = useTranslations("branchPaymentMappingsPage");
  const tCommon = useTranslations("common");
  const { show } = useToast();

  const [mappings, setMappings] = useState<BranchPaymentMapping[]>([]);
  const [accounts, setAccounts] = useState<ZohoAccountOption[]>([]);
  const [modes, setModes] = useState<ZohoPaymentModeOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<unknown>(null);

  const [branch, setBranch] = useState<SearchableOption | null>(null);
  const [accountId, setAccountId] = useState("");
  const [modeId, setModeId] = useState("");
  const [currency, setCurrency] = useState("AFN");
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<unknown>(null);
  const [validatingId, setValidatingId] = useState<number | null>(null);

  async function load() {
    setLoading(true);
    setLoadError(null);
    try {
      const [m, a, p] = await Promise.all([
        listBranchPaymentMappings(),
        listZohoAccounts("cash"),
        listZohoPaymentModes(),
      ]);
      setMappings(m.data);
      setAccounts(a.data);
      setModes(p.data);
    } catch (error) {
      setLoadError(error);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!branch || !accountId || !modeId) return;
    setSubmitting(true);
    setSubmitError(null);
    try {
      await upsertBranchPaymentMapping({
        branch_id: Number(branch.id),
        zoho_account_id: accountId,
        zoho_payment_mode_id: modeId,
        currency,
      });
      show({ tone: "success", title: tCommon("success") });
      setBranch(null);
      await load();
    } catch (error) {
      setSubmitError(error);
    } finally {
      setSubmitting(false);
    }
  }

  async function onValidate(mapping: BranchPaymentMapping) {
    setValidatingId(mapping.id);
    try {
      await validateBranchPaymentMapping(mapping.branch_id);
      show({ tone: "success", title: tCommon("success") });
      await load();
    } catch (error) {
      show({ tone: "error", title: tCommon("error"), description: error instanceof ApiError ? error.message : undefined });
    } finally {
      setValidatingId(null);
    }
  }

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      <form onSubmit={onSubmit} className="mb-6 rounded-lg border border-border bg-surface-elevated p-4">
        <ValidationSummary error={submitError} />
        {submitError && !(submitError instanceof ApiError && submitError.status === 422) ? (
          <div className="mb-3">
            <ErrorState error={submitError} />
          </div>
        ) : null}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FormField label={t("branch")} required>
            <BranchPicker value={branch} onChange={setBranch} />
          </FormField>
          <FormField label={t("account")} required>
            <Select value={accountId} onChange={(e) => setAccountId(e.target.value)}>
              <option value="">—</option>
              {accounts.map((a) => (
                <option key={a.zoho_account_id} value={a.zoho_account_id}>
                  {a.name} {a.account_type ? `(${a.account_type})` : ""}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label={t("paymentMode")} required>
            <Select value={modeId} onChange={(e) => setModeId(e.target.value)}>
              <option value="">—</option>
              {modes.map((m) => (
                <option key={m.zoho_payment_mode_id} value={m.zoho_payment_mode_id}>
                  {m.name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label={t("currency")} required>
            <Select value={currency} onChange={(e) => setCurrency(e.target.value)}>
              <option value="AFN">AFN</option>
              <option value="USD">USD</option>
            </Select>
          </FormField>
        </div>
        <Button type="submit" className="mt-4" disabled={!branch || !accountId || !modeId || submitting}>
          {t("save")}
        </Button>
      </form>

      {loadError ? (
        <ErrorState error={loadError} onRetry={load} />
      ) : loading ? (
        <p className="text-sm text-muted">{tCommon("loading")}</p>
      ) : mappings.length === 0 ? (
        <p className="text-sm text-muted">{t("empty")}</p>
      ) : (
        <div className="space-y-3">
          {mappings.map((m) => (
            <div key={m.id} className="rounded-lg border border-border bg-surface-elevated p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium text-foreground">
                  {m.branch?.name_en || m.branch?.name_fa || `#${m.branch_id}`}
                </p>
                <StatusBadge status={m.readiness_status ?? undefined} />
              </div>
              <p className="mt-1 text-sm text-muted">
                {m.zoho_account_name} · {m.zoho_payment_mode_name} · {m.currency}
              </p>
              <Button
                className="mt-3"
                size="sm"
                variant="secondary"
                disabled={validatingId === m.id}
                onClick={() => onValidate(m)}
              >
                {t("validate")}
              </Button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
