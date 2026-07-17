"use client";

import { cn } from "@/lib/utils";

/**
 * Forces LTR direction for technical values (IDs, codes, emails, amounts)
 * inside RTL layouts without breaking surrounding Persian text flow.
 */
export function LtrValue({
  children,
  className,
  as: Tag = "span",
}: {
  children: React.ReactNode;
  className?: string;
  as?: "span" | "code" | "div";
}) {
  return (
    <Tag className={cn("inline-block", className)} dir="ltr" style={{ unicodeBidi: "isolate" }}>
      {children}
    </Tag>
  );
}
