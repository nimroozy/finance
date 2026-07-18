import { cn } from "@/lib/utils";

/** Header for a single-record detail page: title, meta line, and actions. */
export function DetailHeader({
  title,
  meta,
  status,
  actions,
  className,
}: {
  title: string;
  meta?: React.ReactNode;
  status?: React.ReactNode;
  actions?: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "mb-6 flex flex-col gap-3 border-b border-border pb-4 sm:flex-row sm:items-start sm:justify-between",
        className,
      )}
    >
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <h1 className="truncate text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
            {title}
          </h1>
          {status}
        </div>
        {meta ? <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">{meta}</div> : null}
      </div>
      {actions ? <div className="flex shrink-0 flex-wrap gap-2">{actions}</div> : null}
    </div>
  );
}
