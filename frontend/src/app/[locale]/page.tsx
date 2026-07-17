"use client";

import { useEffect } from "react";
import { useRouter } from "@/i18n/navigation";
import { useAuthStore } from "@/store/auth-store";
import { postLoginPath } from "@/lib/ui-preferences";
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
    router.replace(postLoginPath(user));
  }, [hydrated, token, user, router]);

  return <LoadingState label={t("loading")} />;
}
