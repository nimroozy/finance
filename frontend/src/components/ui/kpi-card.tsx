import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";

type KpiTone = "neutral" | "primary" | "success" | "warning" | "danger";

export function KpiCard({
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
  const tones: Record<KpiTone, string> = {
    neutral: "border-s-border",
    primary: "border-s-primary",
    success: "border-s-success",
    warning: "border-s-warning",
    danger: "border-s-danger",
  };
  const content = (
    <>
      <p className="text-xs font-medium uppercase tracking-wider text-muted">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-primary">{value}</p>
    </>
  );
  const classes = cn(
    "block rounded-lg border border-s-4 border-border bg-surface-elevated p-5 shadow-[var(--shadow)]",
    tones[tone],
    href && "transition hover:-translate-y-0.5 hover:border-border-strong",
  );

  return href ? <Link href={href} className={classes}>{content}</Link> : <div className={classes}>{content}</div>;
}
