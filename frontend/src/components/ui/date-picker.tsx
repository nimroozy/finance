"use client";

import { Input } from "@/components/ui/form";

/** Native date entry (YYYY-MM-DD), styled consistently and kept LTR. */
export function DatePicker({
  value,
  onChange,
  ...props
}: {
  value: string;
  onChange: (value: string) => void;
} & Omit<React.InputHTMLAttributes<HTMLInputElement>, "value" | "onChange" | "type">) {
  return (
    <Input
      {...props}
      type="date"
      dir="ltr"
      value={value}
      onChange={(event) => onChange(event.target.value)}
      className="text-start"
    />
  );
}
