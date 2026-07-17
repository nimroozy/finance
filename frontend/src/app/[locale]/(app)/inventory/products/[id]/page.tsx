"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { getProduct, type Product } from "@/lib/inventory";
import {
  ErrorWorkspace,
  RecordSummary,
  WorkspaceHeader,
} from "@/components/ops";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { LoadingState, Panel } from "@/components/ui/layout";

export default function ProductDetailPage() {
  const t = useTranslations("inventory");
  const tCommon = useTranslations("common");
  const params = useParams();
  const id = String(params.id);
  const [product, setProduct] = useState<Product | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getProduct(id);
      setProduct(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setProduct(null);
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4" data-testid="product-detail">
      <WorkspaceHeader
        title={product?.name_en || t("productsTitle")}
        subtitle={product?.code}
        actions={
          <Link href="/inventory/products">
            <Button variant="secondary">{t("backToProducts")}</Button>
          </Link>
        }
      />
      {loading ? <LoadingState label={tCommon("loading")} /> : null}
      {error ? <ErrorWorkspace message={error} onRetry={() => void load()} /> : null}
      {product ? (
        <Panel className="p-4">
          <div className="mb-3 flex items-center gap-2">
            <StatusBadge status={product.is_active === false ? "inactive" : "active"} />
            <span className="text-sm text-muted">
              {t(`tracking.${product.tracking_mode}` as "tracking.quantity")}
            </span>
          </div>
          <RecordSummary
            items={[
              { label: t("columns.code"), value: product.code },
              { label: t("fields.sku"), value: product.sku || "—" },
              { label: t("fields.barcode"), value: product.barcode || "—" },
              { label: t("fields.brand"), value: product.brand || "—" },
              { label: t("fields.model"), value: product.model || "—" },
              { label: t("fields.uom"), value: product.uom || "—" },
              { label: t("fields.minStock"), value: String(product.min_stock ?? "—") },
              { label: t("fields.notes"), value: product.notes || "—" },
            ]}
          />
        </Panel>
      ) : null}
    </div>
  );
}
