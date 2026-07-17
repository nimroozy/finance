import { cn } from "@/lib/utils";

export type TimelineItem = {
  id: string;
  title: string;
  description?: string;
  meta?: string;
  tone?: "neutral" | "success" | "warning" | "danger" | "info";
};

const DOT: Record<NonNullable<TimelineItem["tone"]>, string> = {
  neutral: "bg-border-strong",
  success: "bg-success",
  warning: "bg-warning",
  danger: "bg-danger",
  info: "bg-primary",
};

export function Timeline({
  items,
  className,
  emptyLabel,
}: {
  items: TimelineItem[];
  className?: string;
  emptyLabel?: string;
}) {
  if (!items.length) {
    return (
      <p className={cn("py-6 text-center text-sm text-muted", className)}>
        {emptyLabel ?? "—"}
      </p>
    );
  }

  return (
    <ol className={cn("relative space-y-0 border-s border-border ps-6", className)}>
      {items.map((item) => (
        <li key={item.id} className="relative pb-6 last:pb-0">
          <span
            className={cn(
              "absolute -start-[1.55rem] top-1.5 size-2.5 rounded-full ring-4 ring-surface",
              DOT[item.tone ?? "neutral"],
            )}
            aria-hidden
          />
          <p className="text-sm font-medium text-foreground">{item.title}</p>
          {item.description ? (
            <p className="mt-1 text-sm text-muted">{item.description}</p>
          ) : null}
          {item.meta ? <p className="mt-1 text-xs text-muted">{item.meta}</p> : null}
        </li>
      ))}
    </ol>
  );
}
