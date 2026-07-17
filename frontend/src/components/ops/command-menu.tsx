"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { Modal } from "@/components/ui/modal";
import { Input } from "@/components/ui/form";
import { operationsSearch } from "@/lib/operations";
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

function mapHits(data: {
  tickets?: unknown[];
  tasks?: unknown[];
  installations?: unknown[];
}): Hit[] {
  const hits: Hit[] = [];
  for (const row of data.tickets ?? []) {
    const r = asRecord(row);
    if (!r) continue;
    const id = r.id;
    if (id == null) continue;
    hits.push({
      id: `ticket-${id}`,
      group: "tickets",
      label: String(r.ticket_number || r.subject || id),
      href: `/tickets/${id}`,
    });
  }
  for (const row of data.tasks ?? []) {
    const r = asRecord(row);
    if (!r) continue;
    const id = r.id;
    if (id == null) continue;
    hits.push({
      id: `task-${id}`,
      group: "tasks",
      label: String(r.task_number || r.title || id),
      href: `/tasks/${id}`,
    });
  }
  for (const row of data.installations ?? []) {
    const r = asRecord(row);
    if (!r) continue;
    const id = r.id;
    if (id == null) continue;
    hits.push({
      id: `inst-${id}`,
      group: "installations",
      label: String(r.installation_number || r.contact_name || id),
      href: `/installations/${id}`,
    });
  }
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
  const tCommon = useTranslations("common");
  const router = useRouter();
  const [query, setQuery] = useState("");
  const [hits, setHits] = useState<Hit[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

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
          if (!cancelled) setHits(mapHits(res.data ?? {}));
        })
        .catch(() => {
          if (!cancelled) {
            setHits([]);
            setError(tCommon("error"));
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

  return (
    <Modal open={open} title={t("commandMenuTitle")} onClose={() => onOpenChange(false)}>
      <Input
        autoFocus
        type="search"
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder={t("commandMenuPlaceholder")}
        aria-label={t("commandMenuTitle")}
      />
      <div className="mt-3 max-h-72 overflow-y-auto">
        {loading ? (
          <p className="px-1 py-3 text-sm text-muted">{tCommon("loading")}</p>
        ) : error ? (
          <p className="px-1 py-3 text-sm text-danger">{error}</p>
        ) : query.trim().length < 2 ? (
          <p className="px-1 py-3 text-sm text-muted">{t("commandMenuHint")}</p>
        ) : hits.length === 0 ? (
          <p className="px-1 py-3 text-sm text-muted">{t("noResults")}</p>
        ) : (
          <ul className="space-y-1">
            {hits.map((hit) => (
              <li key={hit.id}>
                <button
                  type="button"
                  className={cn(
                    "flex w-full items-center justify-between rounded-md px-3 py-2 text-start text-sm hover:bg-sand-soft",
                  )}
                  onClick={() => {
                    onOpenChange(false);
                    router.push(hit.href);
                  }}
                >
                  <span className="font-medium text-foreground">{hit.label}</span>
                  <span className="text-xs text-muted">{t(hit.group as "tickets")}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Modal>
  );
}
