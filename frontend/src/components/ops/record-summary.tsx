import { cn } from "@/lib/utils";

export type RecordSummaryItem = {
  label: string;
  value: React.ReactNode;
};

export function RecordSummary({
  items,
  className,
  columns = 2,
}: {
  items: RecordSummaryItem[];
  className?: string;
  columns?: 1 | 2 | 3;
}) {
  const cols =
    columns === 1
      ? "grid-cols-1"
      : columns === 3
        ? "grid-cols-1 sm:grid-cols-3"
        : "grid-cols-1 sm:grid-cols-2";

  return (
    <dl className={cn("grid gap-3", cols, className)}>
      {items.map((item) => (
        <div key={item.label} className="min-w-0">
          <dt className="text-xs font-medium uppercase tracking-wider text-muted">{item.label}</dt>
          <dd className="mt-1 text-sm text-foreground">{item.value ?? "—"}</dd>
        </div>
      ))}
    </dl>
  );
}
