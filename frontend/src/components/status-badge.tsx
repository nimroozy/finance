"use client";

import { Badge } from "@/components/ui/badge";

type Tone = "neutral" | "success" | "warning" | "danger" | "info";

const STATUS_TONES: Record<string, Tone> = {
  assigned: "info",
  accepted: "info",
  in_progress: "warning",
  closed: "success",
  cancelled: "danger",
  reassigned: "neutral",
  fully_resolved: "success",
  draft: "neutral",
  published: "info",
  completed: "success",
  active: "info",
  due_soon: "warning",
  due_today: "warning",
  overdue: "danger",
  fulfilled: "success",
  superseded: "neutral",
  pending: "neutral",
  skipped: "neutral",
  ok: "success",
  warning: "warning",
  high_risk: "danger",
  confirmed_local: "info",
  pending_zoho_sync: "warning",
  settled_pending_handover: "warning",
  synced: "success",
  sync_failed: "danger",
  reversed: "danger",
  expired: "neutral",
  syncing: "warning",
  failed: "danger",
  dry_run: "info",
  issued: "success",
  voided: "danger",
  approved: "success",
  rejected: "danger",
};

export function StatusBadge({
  status,
  label,
}: {
  status: string | null | undefined;
  label?: string;
}) {
  if (!status) return <Badge tone="neutral">—</Badge>;
  const tone = STATUS_TONES[status] ?? "neutral";
  return <Badge tone={tone}>{label ?? status.replace(/_/g, " ")}</Badge>;
}
