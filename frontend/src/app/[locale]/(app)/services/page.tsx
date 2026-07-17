"use client";

import { useTranslations } from "next-intl";
import { ServicesListWorkspace } from "@/components/services/services-list";
import { useAuthStore } from "@/store/auth-store";

export default function ServicesIndexPage() {
  const t = useTranslations("services");
  const canCreate = useAuthStore((s) => s.hasPermission("services.create"));

  return (
    <ServicesListWorkspace
      title={t("listTitle")}
      subtitle={t("listSubtitle")}
      createHref={canCreate ? "/services/new" : undefined}
      testId="services-list"
    />
  );
}
