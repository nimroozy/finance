import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";

export type MobileRecordCardProps = {
  title: string;
  subtitle?: string;
  href?: string;
  meta?: React.ReactNode;
  badges?: React.ReactNode;
  actions?: React.ReactNode;
  className?: string;
};

export function MobileRecordCard({
  title,
  subtitle,
  href,
  meta,
  badges,
  actions,
  className,
}: MobileRecordCardProps) {
  const body = (
    <>
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{title}</p>
          {subtitle ? <p className="mt-0.5 truncate text-sm text-muted">{subtitle}</p> : null}
        </div>
        {badges ? <div className="shrink-0">{badges}</div> : null}
      </div>
      {meta ? <div className="mt-2 text-xs text-muted">{meta}</div> : null}
      {actions ? <div className="mt-3 flex flex-wrap gap-2">{actions}</div> : null}
    </>
  );

  const classes = cn(
    "block rounded-lg border border-border bg-surface-elevated p-4 shadow-[var(--shadow)]",
    href && "transition hover:border-border-strong",
    className,
  );

  return href ? (
    <Link href={href} className={classes}>
      {body}
    </Link>
  ) : (
    <div className={classes}>{body}</div>
  );
}
