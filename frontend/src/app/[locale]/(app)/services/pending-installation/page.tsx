"use client";

import { useTranslations } from "next-intl";
import { ServicesListWorkspace } from "@/components/services/services-list";

export default function PendingInstallationPage() {
  const t = useTranslations("services");
  return (
    <ServicesListWorkspace
      title={t("pendingInstallTitle")}
      subtitle={t("pendingInstallSubtitle")}
      fixedFilters={{ operational_status: "pending_install" }}
      showStatusFilters={false}
      testId="services-pending-install"
    />
  );
}
