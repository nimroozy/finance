"use client";

import { Input } from "@/components/ui/form";

/** Phone/WhatsApp number entry, always LTR regardless of page direction. */
export function PhoneInput({
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
      type="tel"
      dir="ltr"
      inputMode="tel"
      value={value}
      onChange={(event) => onChange(event.target.value)}
      className="text-start"
    />
  );
}
