"use client";

import { useTranslations } from "next-intl";
import { ServicesListWorkspace } from "@/components/services/services-list";

export default function PendingActivationPage() {
  const t = useTranslations("services");
  return (
    <ServicesListWorkspace
      title={t("pendingActivationTitle")}
      subtitle={t("pendingActivationSubtitle")}
      fixedFilters={{ commercial_status: "pending_activation" }}
      showStatusFilters={false}
      testId="services-pending-activation"
    />
  );
}
