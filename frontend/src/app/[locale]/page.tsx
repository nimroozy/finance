"use client";

import { useEffect } from "react";
import { useRouter } from "@/i18n/navigation";
import { useAuthStore } from "@/store/auth-store";
import { LoadingState } from "@/components/ui/layout";
import { useTranslations } from "next-intl";

export default function LocaleHomePage() {
  const router = useRouter();
  const t = useTranslations("common");
  const { token, user, hydrated } = useAuthStore();

  useEffect(() => {
    if (!hydrated) return;
    if (!token || !user) {
      router.replace("/login");
      return;
    }
    if (user.force_password_change) {
      router.replace("/change-password");
      return;
    }
    router.replace("/dashboard");
  }, [hydrated, token, user, router]);

  return <LoadingState label={t("loading")} />;
}
