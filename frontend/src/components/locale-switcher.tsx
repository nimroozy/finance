"use client";

import { useLocale } from "next-intl";
import { usePathname, useRouter } from "@/i18n/navigation";
import { routing, type AppLocale } from "@/i18n/routing";
import { Select } from "@/components/ui/form";

export function LocaleSwitcher({ className }: { className?: string }) {
  const locale = useLocale();
  const pathname = usePathname();
  const router = useRouter();

  return (
    <Select
      aria-label="Language"
      className={className ?? "w-auto min-w-24"}
      value={locale}
      onChange={(e) => {
        const next = e.target.value as AppLocale;
        router.replace(pathname, { locale: next });
      }}
    >
      {routing.locales.map((code) => (
        <option key={code} value={code}>
          {code === "en" ? "English" : "فارسی"}
        </option>
      ))}
    </Select>
  );
}
