"use client";

import { Input } from "@/components/ui/form";
import { cn } from "@/lib/utils";

/**
 * Decimal money entry. Display-only formatting — the raw numeric string is
 * what's passed to onChange/submitted, so no financial calculation logic
 * lives here.
 */
export function MoneyInput({
  value,
  onChange,
  currency,
  className,
  ...props
}: {
  value: string;
  onChange: (value: string) => void;
  currency?: string | null;
} & Omit<React.InputHTMLAttributes<HTMLInputElement>, "value" | "onChange" | "type">) {
  return (
    <div className="relative">
      <Input
        {...props}
        type="text"
        inputMode="decimal"
        dir="ltr"
        value={value}
        onChange={(event) => {
          const next = event.target.value;
          if (next === "" || /^\d*\.?\d{0,2}$/.test(next)) {
            onChange(next);
          }
        }}
        className={cn("text-end font-medium tabular-nums", currency && "pe-14", className)}
      />
      {currency ? (
        <span className="pointer-events-none absolute inset-y-0 end-3 flex items-center text-xs text-muted">
          {currency}
        </span>
      ) : null}
    </div>
  );
}
