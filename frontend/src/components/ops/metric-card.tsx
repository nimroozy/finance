import { KpiCard } from "@/components/ui/kpi-card";

type KpiTone = "neutral" | "primary" | "success" | "warning" | "danger";

/** Alias of KpiCard with href support for ops dashboards. */
export function MetricCard({
  label,
  value,
  tone = "neutral",
  href,
}: {
  label: string;
  value: React.ReactNode;
  tone?: KpiTone;
  href?: string;
}) {
  return <KpiCard label={label} value={value} tone={tone} href={href} />;
}
