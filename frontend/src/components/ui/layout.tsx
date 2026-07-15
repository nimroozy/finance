import { cn } from "@/lib/utils";

export function PageHeader({
  title,
  subtitle,
  actions,
}: {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-primary">
          {title}
        </h1>
        {subtitle ? (
          <p className="mt-1 text-sm text-muted">{subtitle}</p>
        ) : null}
      </div>
      {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
    </div>
  );
}

export function Panel({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "rounded-lg border border-border bg-surface-elevated shadow-[var(--shadow)]",
        className,
      )}
    >
      {children}
    </div>
  );
}

export function Alert({
  children,
  tone = "danger",
}: {
  children: React.ReactNode;
  tone?: "danger" | "success" | "warning" | "info";
}) {
  const tones = {
    danger: "border-danger/30 bg-danger-soft text-danger",
    success: "border-success/30 bg-success-soft text-success",
    warning: "border-warning/30 bg-warning-soft text-warning",
    info: "border-primary/20 bg-primary/5 text-primary",
  };

  return (
    <div className={cn("rounded-md border px-3 py-2 text-sm", tones[tone])} role="alert">
      {children}
    </div>
  );
}

export function LoadingState({ label }: { label: string }) {
  return (
    <div className="flex min-h-40 items-center justify-center text-sm text-muted">
      {label}
    </div>
  );
}

export function EmptyState({ label }: { label: string }) {
  return (
    <div className="flex min-h-32 items-center justify-center px-4 text-sm text-muted">
      {label}
    </div>
  );
}
