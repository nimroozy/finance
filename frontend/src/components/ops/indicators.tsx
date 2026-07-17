import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

export function SlaIndicator({
  status,
  label,
  dueAt,
  className,
}: {
  status?: string | null;
  label?: string;
  dueAt?: string | null;
  className?: string;
}) {
  const normalized = (status || "").toLowerCase();
  const tone =
    normalized.includes("breach") || normalized === "breached"
      ? "danger"
      : normalized.includes("risk") || normalized === "at_risk"
        ? "warning"
        : normalized.includes("pause")
          ? "neutral"
          : "success";

  return (
    <span className={cn("inline-flex items-center gap-2", className)}>
      <Badge tone={tone}>{label ?? status ?? "SLA"}</Badge>
      {dueAt ? <span className="text-xs text-muted">{dueAt}</span> : null}
    </span>
  );
}

const PRIORITY_TONE: Record<string, "neutral" | "info" | "warning" | "danger"> = {
  low: "neutral",
  normal: "info",
  medium: "info",
  high: "warning",
  urgent: "danger",
  critical: "danger",
};

export function PriorityIndicator({
  priority,
  label,
  className,
}: {
  priority?: string | null;
  label?: string;
  className?: string;
}) {
  if (!priority) return <Badge className={className} tone="neutral">—</Badge>;
  const tone = PRIORITY_TONE[priority.toLowerCase()] ?? "neutral";
  return (
    <Badge className={className} tone={tone}>
      {label ?? priority.replace(/_/g, " ")}
    </Badge>
  );
}
