"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getEquipment, moveEquipment, type Equipment } from "@/lib/inventory";
import { useAuthStore } from "@/store/auth-store";
import {
  AttachmentGallery,
  ErrorWorkspace,
  RecordSummary,
  WorkspaceHeader,
  type AttachmentItem,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/form";
import { Alert, LoadingState, Panel } from "@/components/ui/layout";

export default function EquipmentDetailPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id);
  const canManage = useAuthStore((s) => s.hasPermission("inventory.equipment.manage"));

  const [item, setItem] = useState<Equipment | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toLocation, setToLocation] = useState("");
  const [busy, setBusy] = useState(false);
  const [photos, setPhotos] = useState<AttachmentItem[]>([]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getEquipment(id);
      setItem(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setItem(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onMove() {
    if (!toLocation || busy) return;
    setBusy(true);
    setError(null);
    try {
      const res = await moveEquipment(id, { to_location_id: Number(toLocation) });
      setItem(res.data);
      setToLocation("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4" data-testid="equipment-detail">
      <WorkspaceHeader
        title={item?.equipment_number || t("equipmentTitle")}
        subtitle={item?.serial_number}
        actions={
          <Link href="/inventory/equipment">
            <Button variant="secondary">{t("backToEquipment")}</Button>
          </Link>
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <Alert tone="danger">{error}</Alert> : null}
      {error && !item ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {item ? (
        <>
          <Panel className="p-4">
            <div className="mb-3">
              <StatusBadge status={item.status} />
            </div>
            <RecordSummary
              items={[
                { label: t("columns.product"), value: item.product?.name_en || String(item.product_id) },
                { label: t("columns.serial"), value: item.serial_number },
                { label: t("fields.mac"), value: item.mac_address || "—" },
                { label: t("columns.location"), value: item.location?.name || String(item.location_id ?? "—") },
                { label: t("fields.condition"), value: item.condition || "—" },
              ]}
            />
          </Panel>
          {canManage ? (
            <Panel className="space-y-3 p-4">
              <h3 className="text-sm font-semibold">{t("actions.move")}</h3>
              <div className="flex flex-wrap gap-2">
                <Input
                  type="number"
                  placeholder={t("fields.toLocationId")}
                  value={toLocation}
                  onChange={(e) => setToLocation(e.target.value)}
                  className="max-w-xs"
                />
                <Button onClick={() => void onMove()} disabled={busy || !toLocation}>
                  {t("actions.move")}
                </Button>
              </div>
            </Panel>
          ) : null}
          <AttachmentGallery
            items={photos}
            accept="image/*"
            capture="environment"
            uploadLabel={t("actions.damagePhoto")}
            onUpload={async (file) => {
              setPhotos((prev) => [
                ...prev,
                {
                  id: `${Date.now()}`,
                  name: file.name,
                  meta: t("actions.localPhoto"),
                },
              ]);
            }}
          />
        </>
      ) : null}
    </div>
  );
}
