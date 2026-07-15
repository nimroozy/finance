"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { listBranches } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { listUnmappedCustomers, mapCustomerBranch } from "@/lib/customers";
import type { Branch, Customer } from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Label, Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function ZohoUnmappedPage() {
  const t = useTranslations("zohoUnmapped");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("customers.manage"));

  const [customers, setCustomers] = useState<Customer[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [mapTarget, setMapTarget] = useState<Customer | null>(null);
  const [branchId, setBranchId] = useState("");
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const load = useCallback(
    async (page = 1) => {
      setLoading(true);
      setError(null);
      try {
        const res = await listUnmappedCustomers(page);
        setCustomers(res.data);
        setMeta({
          current_page: res.meta?.current_page ?? 1,
          last_page: res.meta?.last_page ?? 1,
        });
      } catch (err) {
        setError(err instanceof ApiError ? err.message : tCommon("error"));
      } finally {
        setLoading(false);
      }
    },
    [tCommon],
  );

  useEffect(() => {
    void load(1);
  }, [load]);

  async function openMap(customer: Customer) {
    setMapTarget(customer);
    setBranchId("");
    setFormError(null);
    try {
      const res = await listBranches(1);
      setBranches(res.data);
      if (res.data[0]) setBranchId(String(res.data[0].id));
    } catch {
      setBranches([]);
    }
  }

  async function onMap(e: React.FormEvent) {
    e.preventDefault();
    if (!mapTarget || !branchId) return;
    setSaving(true);
    setFormError(null);
    try {
      await mapCustomerBranch(mapTarget.id, Number(branchId));
      setMapTarget(null);
      setSuccess(t("mapSuccess"));
      await load(meta.current_page);
    } catch (err) {
      setFormError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <Link
            href="/zoho"
            className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm hover:bg-sand-soft"
          >
            {t("backToZoho")}
          </Link>
        }
      />

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

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : customers.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("contact")}</th>
                  <th className="px-4 py-3 font-medium">{t("company")}</th>
                  <th className="px-4 py-3 font-medium">
                    {t("customerNumber")}
                  </th>
                  {canManage ? (
                    <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {customers.map((customer) => (
                  <tr
                    key={customer.id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-3 font-medium">
                      {customer.contact_name || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {customer.company_name || "—"}
                    </td>
                    <td className="px-4 py-3">
                      {customer.customer_number || "—"}
                    </td>
                    {canManage ? (
                      <td className="px-4 py-3">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => void openMap(customer)}
                        >
                          {t("map")}
                        </Button>
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", {
                page: meta.current_page,
                total: meta.last_page,
              })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page <= 1 || loading}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page || loading}
                onClick={() => void load(meta.current_page + 1)}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        ) : null}
      </Panel>

      <Modal
        open={Boolean(mapTarget)}
        title={t("mapTitle")}
        onClose={() => setMapTarget(null)}
      >
        <form onSubmit={onMap} className="space-y-4">
          {formError ? <Alert>{formError}</Alert> : null}
          <p className="text-sm text-muted">
            {mapTarget?.contact_name || mapTarget?.company_name}
          </p>
          <div>
            <Label htmlFor="branch">{t("selectBranch")}</Label>
            <Select
              id="branch"
              value={branchId}
              onChange={(e) => setBranchId(e.target.value)}
              required
            >
              <option value="">—</option>
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.code} —{" "}
                  {locale === "fa" ? branch.name_fa : branch.name_en}
                </option>
              ))}
            </Select>
          </div>
          <div className="flex justify-end gap-2">
            <Button
              variant="secondary"
              type="button"
              onClick={() => setMapTarget(null)}
            >
              {tCommon("cancel")}
            </Button>
            <Button type="submit" disabled={saving || !branchId}>
              {t("map")}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
