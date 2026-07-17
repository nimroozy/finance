"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ApiError } from "@/lib/api";
import {
  createGoodsReceipt,
  listLocations,
  listProducts,
  receiveEquipment,
  type InventoryLocation,
  type Product,
} from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
import { BranchPicker, WorkspaceHeader } from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

export default function ReceivingPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const defaultBranch = useAuthStore((s) => s.user?.branches?.[0]);
  const canReceive = useAuthStore((s) => s.hasPermission("inventory.receiving.manage"));
  const canEquip = useAuthStore((s) => s.hasPermission("inventory.equipment.manage"));

  const [branchId, setBranchId] = useState(defaultBranch ? String(defaultBranch.id) : "");
  const [branchLabel, setBranchLabel] = useState<string | null>(
    defaultBranch
      ? (locale === "fa" ? defaultBranch.name_fa : defaultBranch.name_en) || defaultBranch.name_en
      : null,
  );
  const [locations, setLocations] = useState<InventoryLocation[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [locationId, setLocationId] = useState("");
  const [productId, setProductId] = useState("");
  const [qty, setQty] = useState("1");
  const [serial, setSerial] = useState("");
  const [barcode, setBarcode] = useState("");
  const [mode, setMode] = useState<"qty" | "serial">("qty");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    void listLocations({ per_page: 100, branch_id: branchId || undefined })
      .then((res) => setLocations(res.data))
      .catch(() => setLocations([]));
    void listProducts({ per_page: 100 })
      .then((res) => setProducts(res.data))
      .catch(() => setProducts([]));
  }, [branchId]);

  async function onReceive() {
    if (busy || !branchId || !locationId || !productId) {
      setError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    try {
      if (mode === "serial") {
        if (!canEquip) throw new ApiError(t("permissionDenied"), 403);
        if (!serial.trim()) {
          setError(t("requiredFields"));
          setBusy(false);
          return;
        }
        await receiveEquipment({
          branch_id: Number(branchId),
          location_id: Number(locationId),
          product_id: Number(productId),
          serial_number: serial.trim(),
          barcode: barcode.trim() || undefined,
        });
        setSuccess(t("receiveSerialSuccess"));
        setSerial("");
      } else {
        if (!canReceive) throw new ApiError(t("permissionDenied"), 403);
        await createGoodsReceipt({
          branch_id: Number(branchId),
          location_id: Number(locationId),
          items: [{ product_id: Number(productId), qty: Number(qty) || 1 }],
        });
        setSuccess(t("receiveQtySuccess"));
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  if (!canReceive && !canEquip) {
    return (
      <div className="space-y-4" data-testid="permission-denied">
        <WorkspaceHeader title={t("receivingTitle")} subtitle={t("receivingSubtitle")} />
        <Alert tone="danger">{t("permissionDenied")}</Alert>
      </div>
    );
  }

  return (
    <div className="space-y-4" data-testid="inventory-receiving">
      <WorkspaceHeader title={t("receivingTitle")} subtitle={t("receivingSubtitle")} />
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}
      <Panel className="space-y-4 p-4">
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant={mode === "qty" ? "primary" : "secondary"}
            className="min-h-12 flex-1 sm:flex-none"
            onClick={() => setMode("qty")}
          >
            {t("receiveModeQty")}
          </Button>
          <Button
            type="button"
            variant={mode === "serial" ? "primary" : "secondary"}
            className="min-h-12 flex-1 sm:flex-none"
            onClick={() => setMode("serial")}
            data-testid="receive-mode-serial"
          >
            {t("receiveModeSerial")}
          </Button>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <BranchPicker
            value={branchId || null}
            selectedLabel={branchLabel}
            onChange={(opt) => {
              setBranchId(opt ? String(opt.id) : "");
              setBranchLabel(opt?.label ?? null);
            }}
          />
          <Select
            value={locationId}
            onChange={(e) => setLocationId(e.target.value)}
            aria-label={t("columns.location")}
            data-testid="receive-location"
          >
            <option value="">{t("fields.selectLocation")}</option>
            {locations.map((loc) => (
              <option key={loc.id} value={loc.id}>
                {loc.name} ({loc.code})
              </option>
            ))}
          </Select>
          <Select
            value={productId}
            onChange={(e) => setProductId(e.target.value)}
            aria-label={t("columns.product")}
            data-testid="receive-product"
          >
            <option value="">{t("fields.selectProduct")}</option>
            {products.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name_en} ({p.code})
              </option>
            ))}
          </Select>
          {mode === "qty" ? (
            <Input
              type="number"
              min={0.001}
              step="any"
              value={qty}
              onChange={(e) => setQty(e.target.value)}
              aria-label={t("columns.qty")}
              data-testid="receive-qty"
            />
          ) : (
            <>
              <Input
                value={serial}
                onChange={(e) => setSerial(e.target.value)}
                placeholder={t("columns.serial")}
                data-testid="receive-serial"
              />
              <Input
                value={barcode}
                onChange={(e) => setBarcode(e.target.value)}
                placeholder={t("fields.barcodeFallback")}
                data-testid="receive-barcode"
              />
            </>
          )}
        </div>
        <Button
          className="min-h-14 w-full text-base"
          disabled={busy}
          onClick={() => void onReceive()}
          data-testid="receive-submit"
        >
          {busy ? tCommon("loading") : t("actions.receive")}
        </Button>
      </Panel>
    </div>
  );
}
