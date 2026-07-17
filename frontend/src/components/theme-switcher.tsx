"use client";

import { Monitor, Moon, Sun } from "lucide-react";
import { useTranslations } from "next-intl";
import { useTheme } from "@/components/theme-provider";
import type { UiTheme } from "@/lib/ui-preferences";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const OPTIONS: { id: UiTheme; icon: typeof Sun }[] = [
  { id: "light", icon: Sun },
  { id: "dark", icon: Moon },
  { id: "system", icon: Monitor },
];

export function ThemeSwitcher({ className }: { className?: string }) {
  const t = useTranslations("shell");
  const { theme, setTheme } = useTheme();

  return (
    <div
      className={cn("inline-flex items-center gap-0.5 rounded-md border border-border p-0.5", className)}
      role="group"
      aria-label={t("theme")}
      data-testid="theme-switcher"
    >
      {OPTIONS.map(({ id, icon: Icon }) => (
        <Button
          key={id}
          type="button"
          variant="ghost"
          size="sm"
          className={cn(
            "h-8 w-8 p-0",
            theme === id && "bg-sand-soft text-primary",
          )}
          aria-label={t(`theme_${id}`)}
          aria-pressed={theme === id}
          onClick={() => void setTheme(id)}
        >
          <Icon className="h-4 w-4" aria-hidden />
        </Button>
      ))}
    </div>
  );
}
