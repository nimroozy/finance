import type { LucideIcon } from "lucide-react";
import { Inbox } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export function EmptyState({
  icon: Icon = Inbox,
  title,
  description,
  action,
  className,
}: {
  icon?: LucideIcon;
  title: string;
  description?: string;
  action?: { label: string; onClick: () => void };
  className?: string;
}) {
  return (
    <div
      className={cn(
        "flex min-h-40 flex-col items-center justify-center gap-2 px-4 py-10 text-center",
        className,
      )}
    >
      <Icon className="h-8 w-8 text-muted/60" aria-hidden />
      <p className="text-sm font-medium text-foreground">{title}</p>
      {description ? (
        <p className="max-w-sm text-sm text-muted">{description}</p>
      ) : null}
      {action ? (
        <Button variant="secondary" size="sm" className="mt-2" onClick={action.onClick}>
          {action.label}
        </Button>
      ) : null}
    </div>
  );
}
