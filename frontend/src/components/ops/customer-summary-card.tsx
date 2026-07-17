import { Link } from "@/i18n/navigation";
import { Badge } from "@/components/ui/badge";
import { Panel } from "@/components/ui/layout";
import { cn } from "@/lib/utils";

export type CustomerSummaryCardProps = {
  customerNumber?: string | null;
  name?: string | null;
  phone?: string | null;
  branch?: string | null;
  openTicketsCount?: number | null;
  href?: string;
  className?: string;
};

export function CustomerSummaryCard({
  customerNumber,
  name,
  phone,
  branch,
  openTicketsCount,
  href,
  className,
}: CustomerSummaryCardProps) {
  const content = (
    <Panel className={cn("p-4", className)}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-medium uppercase tracking-wider text-muted">
            {customerNumber || "—"}
          </p>
          <p className="mt-1 truncate text-base font-semibold text-primary">{name || "—"}</p>
          <div className="mt-2 space-y-1 text-sm text-muted">
            {phone ? <p>{phone}</p> : null}
            {branch ? <p>{branch}</p> : null}
          </div>
        </div>
        {openTicketsCount != null ? (
          <Badge tone={openTicketsCount > 0 ? "warning" : "neutral"}>
            {openTicketsCount}
          </Badge>
        ) : null}
      </div>
    </Panel>
  );

  return href ? (
    <Link href={href} className="block transition hover:-translate-y-0.5">
      {content}
    </Link>
  ) : (
    content
  );
}
