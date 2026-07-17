"use client";

import { useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { Modal } from "@/components/ui/modal";
import { Input } from "@/components/ui/form";
import { operationsSearch } from "@/lib/operations";
import { APP_CATALOG, isAppVisible } from "@/config/app-catalog";
import { useAuthStore } from "@/store/auth-store";
import { recordRecentApp } from "@/lib/ui-preferences";
import { cn } from "@/lib/utils";

type Hit = {
  id: string;
  label: string;
  href: string;
  group: string;
};

function asRecord(v: unknown): Record<string, unknown> | null {
  return v && typeof v === "object" ? (v as Record<string, unknown>) : null;
}

function pushHits(
  hits: Hit[],
  rows: unknown[] | undefined,
  group: string,
  hrefFor: (r: Record<string, unknown>) => string | null,
  labelFor: (r: Record<string, unknown>) => string,
) {
  for (const row of rows ?? []) {
    const r = asRecord(row);
    if (!r) continue;
    const id = r.id;
    if (id == null) continue;
    const href = hrefFor(r);
    if (!href) continue;
    hits.push({
      id: `${group}-${id}`,
      group,
      label: labelFor(r),
      href,
    });
  }
}

function mapHits(data: Record<string, unknown[] | undefined>): Hit[] {
  const hits: Hit[] = [];
  pushHits(
    hits,
    data.customers,
    "customers",
    (r) => `/customers/${r.id}`,
    (r) => String(r.contact_name || r.customer_number || r.company_name || r.id),
  );
  pushHits(
    hits,
    data.services,
    "services",
    (r) => `/services/${r.id}`,
    (r) => String(r.service_number || r.circuit_id || r.id),
  );
  pushHits(
    hits,
    data.leads,
    "leads",
    (r) => `/crm/leads/${r.id}`,
    (r) => String(r.lead_number || r.company || r.contact_person || r.id),
  );
  pushHits(
    hits,
    data.tickets,
    "tickets",
    (r) => `/tickets/${r.id}`,
    (r) => String(r.ticket_number || r.subject || r.id),
  );
  pushHits(
    hits,
    data.tasks,
    "tasks",
    (r) => `/tasks/${r.id}`,
    (r) => String(r.task_number || r.title || r.id),
  );
  pushHits(
    hits,
    data.installations,
    "installations",
    (r) => `/installations/${r.id}`,
    (r) => String(r.installation_number || r.prospect_name || r.contact_name || r.id),
  );
  pushHits(
    hits,
    data.payments,
    "payments",
    (r) => `/payments/${r.id}`,
    (r) => String(r.payment_reference || r.id),
  );
  pushHits(
    hits,
    data.products,
    "products",
    (r) => `/inventory/products/${r.id}`,
    (r) => String(r.code || r.name_en || r.id),
  );
  pushHits(
    hits,
    data.equipment,
    "equipment",
    (r) => `/inventory/equipment/${r.id}`,
    (r) => String(r.equipment_number || r.serial_number || r.mac_address || r.id),
  );
  pushHits(
    hits,
    data.transfers,
    "transfers",
    (r) => `/inventory/transfers/${r.id}`,
    (r) => String(r.transfer_number || r.id),
  );
  pushHits(
    hits,
    data.sites,
    "sites",
    (r) => `/sites/${r.id}`,
    (r) => String(r.code || r.name || r.id),
  );
  pushHits(
    hits,
    data.towers,
    "towers",
    (r) => `/sites/towers/${r.id}`,
    (r) => String(r.code || r.id),
  );
  pushHits(
    hits,
    data.users,
    "users",
    (r) => `/users/${r.id}`,
    (r) => String(r.name || r.username || r.email || r.id),
  );
  return hits;
}

export function CommandMenu({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const t = useTranslations("opsUi");
  const tApps = useTranslations("apps");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const { hasAnyPermission } = useAuthStore();
  const [query, setQuery] = useState("");
  const [hits, setHits] = useState<Hit[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const appHits = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (q.length < 1) return [] as Hit[];
    return APP_CATALOG.filter((app) => isAppVisible(app, hasAnyPermission))
      .filter((app) => {
        const name = tApps(app.nameKey).toLowerCase();
        const desc = tApps(app.descriptionKey).toLowerCase();
        return app.id.includes(q) || name.includes(q) || desc.includes(q);
      })
      .slice(0, 8)
      .map((app) => ({
        id: `app-${app.id}`,
        group: "apps",
        label: tApps(app.nameKey),
        href: app.href,
      }));
  }, [query, hasAnyPermission, tApps]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        onOpenChange(!open);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onOpenChange]);

  useEffect(() => {
    if (!open) {
      setQuery("");
      setHits([]);
      setError(null);
      return;
    }
    const q = query.trim();
    if (q.length < 2) {
      setHits([]);
      return;
    }
    let cancelled = false;
    const handle = window.setTimeout(() => {
      setLoading(true);
      setError(null);
      void operationsSearch(q)
        .then((res) => {
          if (!cancelled) setHits(mapHits((res.data ?? {}) as Record<string, unknown[] | undefined>));
        })
        .catch(() => {
          if (!cancelled) {
            setHits([]);
            setError(null);
          }
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    }, 250);
    return () => {
      cancelled = true;
      window.clearTimeout(handle);
    };
  }, [open, query, tCommon]);

  const combined = [...appHits, ...hits];

  return (
    <Modal open={open} title={t("commandMenuTitle")} onClose={() => onOpenChange(false)}>
      <Input
        autoFocus
        type="search"
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder={t("commandMenuPlaceholder")}
        aria-label={t("commandMenuTitle")}
        data-testid="command-menu-input"
      />
      <div className="mt-3 max-h-72 overflow-y-auto">
        {loading && hits.length === 0 && appHits.length === 0 ? (
          <p className="px-1 py-3 text-sm text-muted">{tCommon("loading")}</p>
        ) : error ? (
          <p className="px-1 py-3 text-sm text-danger">{error}</p>
        ) : query.trim().length < 1 ? (
          <p className="px-1 py-3 text-sm text-muted">{t("commandMenuHint")}</p>
        ) : combined.length === 0 ? (
          <p className="px-1 py-3 text-sm text-muted">{t("noResults")}</p>
        ) : (
          <ul className="space-y-1" data-testid="command-menu-results">
            {combined.map((hit) => (
              <li key={hit.id}>
                <button
                  type="button"
                  className={cn(
                    "flex w-full items-center justify-between rounded-md px-3 py-2 text-start text-sm hover:bg-sand-soft",
                  )}
                  onClick={() => {
                    onOpenChange(false);
                    if (hit.id.startsWith("app-")) {
                      void recordRecentApp(hit.id.replace(/^app-/, ""));
                    }
                    router.push(hit.href);
                  }}
                >
                  <span className="font-medium text-foreground">{hit.label}</span>
                  <span className="text-xs text-muted">
                    {hit.group === "apps" ? tApps("group_operations") : t(hit.group as "tickets")}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Modal>
  );
}
