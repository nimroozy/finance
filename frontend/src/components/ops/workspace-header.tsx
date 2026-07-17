import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

export type WorkspaceHeaderProps = {
  title: string;
  subtitle?: string;
  badges?: Array<{ label: string; tone?: "neutral" | "success" | "warning" | "danger" | "info" }>;
  actions?: React.ReactNode;
  meta?: React.ReactNode;
  className?: string;
};

export function WorkspaceHeader({
  title,
  subtitle,
  badges,
  actions,
  meta,
  className,
}: WorkspaceHeaderProps) {
  return (
    <header className={cn("mb-6 space-y-3", className)}>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <h1 className="text-2xl font-semibold tracking-tight text-primary">{title}</h1>
          {subtitle ? <p className="mt-1 text-sm text-muted">{subtitle}</p> : null}
          {badges && badges.length > 0 ? (
            <div className="mt-2 flex flex-wrap gap-2">
              {badges.map((b) => (
                <Badge key={b.label} tone={b.tone ?? "neutral"}>
                  {b.label}
                </Badge>
              ))}
            </div>
          ) : null}
        </div>
        {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
      </div>
      {meta ? (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-border pt-3 text-sm text-muted">
          {meta}
        </div>
      ) : null}
    </header>
  );
}
