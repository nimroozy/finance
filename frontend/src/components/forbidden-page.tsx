"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Button } from "@/components/ui/button";
import { Panel } from "@/components/ui/layout";

export function ForbiddenPage({
  message,
  showAppsLink = true,
}: {
  message?: string;
  showAppsLink?: boolean;
} = {}) {
  const t = useTranslations("shell");

  return (
    <div className="mx-auto flex min-h-[50vh] max-w-lg flex-col justify-center px-4" data-testid="forbidden-page">
      <Panel className="space-y-4 p-8 text-center">
        <p className="text-5xl font-semibold tracking-tight text-primary">403</p>
        <h1 className="text-xl font-semibold text-foreground">{t("forbiddenTitle")}</h1>
        <p className="text-sm text-muted">{message ?? t("forbiddenBody")}</p>
        {showAppsLink ? (
          <div className="pt-2">
            <Link href="/apps">
              <Button type="button">{t("backToApps")}</Button>
            </Link>
          </div>
        ) : null}
      </Panel>
    </div>
  );
}

export default function ForbiddenPageRoute() {
  return <ForbiddenPage />;
}
