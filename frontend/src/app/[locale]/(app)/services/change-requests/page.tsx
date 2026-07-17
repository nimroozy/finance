"use client";

import { useTranslations } from "next-intl";
import { ServicesListWorkspace } from "@/components/services/services-list";
import { Alert } from "@/components/ui/layout";

export default function ChangeRequestsPage() {
  const t = useTranslations("services");
  return (
    <div className="space-y-4" data-testid="services-change-requests">
      <Alert>{t("changeRequestsHint")}</Alert>
      <ServicesListWorkspace
        title={t("changeRequestsTitle")}
        subtitle={t("changeRequestsSubtitle")}
        fixedFilters={{ commercial_status: "active" }}
        showStatusFilters={false}
        testId="services-change-requests-list"
      />
    </div>
  );
}
