"use client";

import { useTranslations } from "next-intl";
import { ServicesListWorkspace } from "@/components/services/services-list";

export default function SuspendedServicesPage() {
  const t = useTranslations("services");
  return (
    <ServicesListWorkspace
      title={t("suspendedTitle")}
      subtitle={t("suspendedSubtitle")}
      fixedFilters={{ commercial_status: "suspended" }}
      showStatusFilters={false}
      testId="services-suspended"
    />
  );
}
