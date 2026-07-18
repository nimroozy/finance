import { cn } from "@/lib/utils";

/** Row beneath a PageHeader holding search/filters (start) and actions (end). */
export function PageToolbar({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between",
        className,
      )}
    >
      {children}
    </div>
  );
}
