"use client";

import { useTranslations } from "next-intl";
import { ServicesListWorkspace } from "@/components/services/services-list";

export default function FinanceHoldServicesPage() {
  const t = useTranslations("services");
  return (
    <ServicesListWorkspace
      title={t("financeHoldTitle")}
      subtitle={t("financeHoldSubtitle")}
      fixedFilters={{ billing_status: "on_hold" }}
      showStatusFilters={false}
      testId="services-finance-hold"
    />
  );
}
