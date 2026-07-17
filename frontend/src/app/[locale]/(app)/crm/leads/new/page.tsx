"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  CUSTOMER_TYPES,
  createLead,
  listLeadSources,
  type LeadSource,
} from "@/lib/crm";
import { useAuthStore } from "@/store/auth-store";
import { BranchPicker, UserPicker, WorkspaceHeader } from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

export default function NewCrmLeadPage() {
  const t = useTranslations("crm");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const defaultBranch = useAuthStore((s) => s.user?.branches?.[0]);

  const [sources, setSources] = useState<LeadSource[]>([]);
  const [branchId, setBranchId] = useState(defaultBranch ? String(defaultBranch.id) : "");
  const [branchLabel, setBranchLabel] = useState<string | null>(
    defaultBranch
      ? (locale === "fa" ? defaultBranch.name_fa : defaultBranch.name_en) ||
        defaultBranch.name_en
      : null,
  );
  const [ownerId, setOwnerId] = useState<string>("");
  const [ownerLabel, setOwnerLabel] = useState<string | null>(null);
  const [salespersonId, setSalespersonId] = useState<string>("");
  const [salespersonLabel, setSalespersonLabel] = useState<string | null>(null);
  const [form, setForm] = useState({
    company: "",
    contact_person: "",
    phone: "",
    whatsapp: "",
    email: "",
    address: "",
    source_code: "",
    campaign: "",
    interested_package: "",
    customer_type: "residential",
    lead_value: "",
    estimated_mrr: "",
    notes: "",
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listLeadSources()
      .then((res) => setSources(res.data))
      .catch(() => setSources([]));
  }, []);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (busy) return;
    if (!branchId || (!form.contact_person.trim() && !form.company.trim())) {
      setError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await createLead({
        branch_id: Number(branchId),
        company: form.company.trim() || undefined,
        contact_person: form.contact_person.trim() || undefined,
        phone: form.phone.trim() || undefined,
        whatsapp: form.whatsapp.trim() || undefined,
        email: form.email.trim() || undefined,
        address: form.address.trim() || undefined,
        source_code: form.source_code || undefined,
        campaign: form.campaign.trim() || undefined,
        interested_package: form.interested_package.trim() || undefined,
        customer_type: form.customer_type || undefined,
        lead_value: form.lead_value ? Number(form.lead_value) : undefined,
        estimated_mrr: form.estimated_mrr ? Number(form.estimated_mrr) : undefined,
        notes: form.notes.trim() || undefined,
        owner_id: ownerId ? Number(ownerId) : undefined,
        salesperson_id: salespersonId ? Number(salespersonId) : undefined,
      });
      router.push(`/crm/leads/${res.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-3xl space-y-4">
      <WorkspaceHeader title={t("newLeadTitle")} subtitle={t("newLeadSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <Panel>
        <form className="grid gap-4 sm:grid-cols-2" onSubmit={onSubmit}>
          <div className="sm:col-span-2">
            <BranchPicker
              value={branchId || null}
              selectedLabel={branchLabel}
              onChange={(opt) => {
                setBranchId(opt ? String(opt.id) : "");
                setBranchLabel(opt?.label ?? null);
              }}
            />
          </div>
          <label className="block text-sm font-medium">
            <span className="mb-1 block">{t("fields.contactPerson")}</span>
            <Input
              value={form.contact_person}
              onChange={(e) => setForm((f) => ({ ...f, contact_person: e.target.value }))}
            />
          </label>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.company")}</label>
            <Input
              value={form.company}
              onChange={(e) => setForm((f) => ({ ...f, company: e.target.value }))}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.phone")}</label>
            <Input
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.whatsapp")}</label>
            <Input
              value={form.whatsapp}
              onChange={(e) => setForm((f) => ({ ...f, whatsapp: e.target.value }))}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.email")}</label>
            <Input
              type="email"
              value={form.email}
              onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.source")}</label>
            <Select
              value={form.source_code}
              onChange={(e) => setForm((f) => ({ ...f, source_code: e.target.value }))}
            >
              <option value="">{tCommon("all")}</option>
              {sources.map((s) => (
                <option key={s.code} value={s.code}>
                  {locale === "fa" && s.name_fa ? s.name_fa : s.name_en}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.customerType")}</label>
            <Select
              value={form.customer_type}
              onChange={(e) => setForm((f) => ({ ...f, customer_type: e.target.value }))}
            >
              {CUSTOMER_TYPES.map((ct) => (
                <option key={ct} value={ct}>
                  {t(`customerTypes.${ct}` as "customerTypes.residential")}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.package")}</label>
            <Input
              value={form.interested_package}
              onChange={(e) => setForm((f) => ({ ...f, interested_package: e.target.value }))}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.campaign")}</label>
            <Input
              value={form.campaign}
              onChange={(e) => setForm((f) => ({ ...f, campaign: e.target.value }))}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.leadValue")}</label>
            <Input
              type="number"
              value={form.lead_value}
              onChange={(e) => setForm((f) => ({ ...f, lead_value: e.target.value }))}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">{t("fields.estimatedMrr")}</label>
            <Input
              type="number"
              value={form.estimated_mrr}
              onChange={(e) => setForm((f) => ({ ...f, estimated_mrr: e.target.value }))}
            />
          </div>
          <div>
            <UserPicker
              label={t("fields.owner")}
              value={ownerId || null}
              selectedLabel={ownerLabel}
              onChange={(opt) => {
                setOwnerId(opt ? String(opt.id) : "");
                setOwnerLabel(opt?.label ?? null);
              }}
            />
          </div>
          <div>
            <UserPicker
              label={t("fields.salesperson")}
              value={salespersonId || null}
              selectedLabel={salespersonLabel}
              onChange={(opt) => {
                setSalespersonId(opt ? String(opt.id) : "");
                setSalespersonLabel(opt?.label ?? null);
              }}
            />
          </div>
          <div className="sm:col-span-2">
            <label className="mb-1 block text-sm font-medium">{t("fields.address")}</label>
            <TextArea
              value={form.address}
              onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
              rows={2}
            />
          </div>
          <div className="sm:col-span-2">
            <label className="mb-1 block text-sm font-medium">{t("fields.notes")}</label>
            <TextArea
              value={form.notes}
              onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
              rows={3}
            />
          </div>
          <div className="flex gap-2 sm:col-span-2">
            <Button type="submit" disabled={busy}>
              {busy ? tCommon("loading") : t("createLead")}
            </Button>
            <Link href="/crm/leads">
              <Button type="button" variant="secondary">
                {tCommon("cancel")}
              </Button>
            </Link>
          </div>
        </form>
      </Panel>
    </div>
  );
}
