"use client";

import { useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link, useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { TRACKING_MODES, createProduct } from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
import { WorkspaceHeader } from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

export default function NewProductPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const canManage = useAuthStore((s) => s.hasPermission("inventory.products.manage"));

  const [form, setForm] = useState({
    name_en: "",
    name_fa: "",
    sku: "",
    barcode: "",
    tracking_mode: "quantity",
    uom: "pcs",
    brand: "",
    model: "",
    min_stock: "",
    notes: "",
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (busy || !canManage) return;
    if (!form.name_en.trim()) {
      setError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await createProduct({
        name_en: form.name_en.trim(),
        name_fa: form.name_fa.trim() || undefined,
        sku: form.sku.trim() || undefined,
        barcode: form.barcode.trim() || undefined,
        tracking_mode: form.tracking_mode,
        uom: form.uom.trim() || undefined,
        brand: form.brand.trim() || undefined,
        model: form.model.trim() || undefined,
        min_stock: form.min_stock ? Number(form.min_stock) : undefined,
        notes: form.notes.trim() || undefined,
        is_active: true,
      });
      router.push(`/inventory/products/${res.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (!canManage) {
    return (
      <div className="space-y-4" data-testid="permission-denied">
        <WorkspaceHeader title={t("newProduct")} subtitle={t("productsSubtitle")} />
        <Alert tone="danger">{t("permissionDenied")}</Alert>
      </div>
    );
  }

  return (
    <div className="space-y-4" data-testid="new-product-page">
      <WorkspaceHeader
        title={t("newProduct")}
        subtitle={t("newProductSubtitle")}
        actions={
          <Link href="/inventory/products">
            <Button variant="secondary">{tCommon("cancel")}</Button>
          </Link>
        }
      />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <Panel>
        <form className="grid gap-4 p-4 sm:grid-cols-2" onSubmit={onSubmit}>
          <label className="block space-y-1 sm:col-span-2">
            <span className="text-sm font-medium">{t("fields.nameEn")}</span>
            <Input
              data-testid="product-name"
              value={form.name_en}
              onChange={(e) => setForm((f) => ({ ...f, name_en: e.target.value }))}
              required
            />
          </label>
          <label className="block space-y-1 sm:col-span-2">
            <span className="text-sm font-medium">{t("fields.nameFa")}</span>
            <Input
              value={form.name_fa}
              onChange={(e) => setForm((f) => ({ ...f, name_fa: e.target.value }))}
              dir={locale === "fa" ? "rtl" : undefined}
            />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.sku")}</span>
            <Input value={form.sku} onChange={(e) => setForm((f) => ({ ...f, sku: e.target.value }))} />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.barcode")}</span>
            <Input
              data-testid="product-barcode"
              value={form.barcode}
              onChange={(e) => setForm((f) => ({ ...f, barcode: e.target.value }))}
            />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.tracking")}</span>
            <Select
              data-testid="product-tracking"
              value={form.tracking_mode}
              onChange={(e) => setForm((f) => ({ ...f, tracking_mode: e.target.value }))}
            >
              {TRACKING_MODES.map((m) => (
                <option key={m} value={m}>
                  {t(`tracking.${m}` as "tracking.quantity")}
                </option>
              ))}
            </Select>
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.uom")}</span>
            <Input value={form.uom} onChange={(e) => setForm((f) => ({ ...f, uom: e.target.value }))} />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.brand")}</span>
            <Input value={form.brand} onChange={(e) => setForm((f) => ({ ...f, brand: e.target.value }))} />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.model")}</span>
            <Input value={form.model} onChange={(e) => setForm((f) => ({ ...f, model: e.target.value }))} />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium">{t("fields.minStock")}</span>
            <Input
              type="number"
              value={form.min_stock}
              onChange={(e) => setForm((f) => ({ ...f, min_stock: e.target.value }))}
            />
          </label>
          <label className="block space-y-1 sm:col-span-2">
            <span className="text-sm font-medium">{t("fields.notes")}</span>
            <TextArea
              value={form.notes}
              onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
              rows={3}
            />
          </label>
          <div className="sm:col-span-2">
            <Button type="submit" disabled={busy} data-testid="save-product">
              {busy ? tCommon("loading") : tCommon("create")}
            </Button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
