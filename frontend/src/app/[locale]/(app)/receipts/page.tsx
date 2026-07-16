"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/form";
import { PageHeader, Panel } from "@/components/ui/layout";

export default function ReceiptsLookupPage() {
  const t = useTranslations("receiptsPage");
  const router = useRouter();
  const [uuid, setUuid] = useState("");

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    const value = uuid.trim();
    if (!value) return;
    router.push(`/payments/${value}`);
  }

  return (
    <div className="space-y-4">
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      <Panel className="max-w-lg p-4">
        <form onSubmit={onSubmit} className="space-y-3">
          <div>
            <Label>{t("paymentUuid")}</Label>
            <Input
              value={uuid}
              onChange={(e) => setUuid(e.target.value)}
              placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
              required
            />
          </div>
          <Button type="submit">{t("open")}</Button>
        </form>
      </Panel>
    </div>
  );
}
