"use client";

import { useTranslations } from "next-intl";
import { ServicesListWorkspace } from "@/components/services/services-list";
import { Alert } from "@/components/ui/layout";

export default function RelocationsPage() {
  const t = useTranslations("services");
  return (
    <div className="space-y-4" data-testid="services-relocations">
      <Alert>{t("relocationsHint")}</Alert>
      <ServicesListWorkspace
        title={t("relocationsTitle")}
        subtitle={t("relocationsSubtitle")}
        fixedFilters={{ commercial_status: "active" }}
        showStatusFilters={false}
        testId="services-relocations-list"
      />
    </div>
  );
}
