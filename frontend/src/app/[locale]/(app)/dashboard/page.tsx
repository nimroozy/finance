"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { getSettings, listBranches, listUsers } from "@/lib/auth";
import { useAuthStore } from "@/store/auth-store";
import { PageHeader, Panel, LoadingState } from "@/components/ui/layout";

export default function DashboardPage() {
  const t = useTranslations("dashboard");
  const tCommon = useTranslations("common");
  const hasAnyPermission = useAuthStore((s) => s.hasAnyPermission);

  const [loading, setLoading] = useState(true);
  const [companyName, setCompanyName] = useState<string | null>(null);
  const [branchCount, setBranchCount] = useState<number | null>(null);
  const [userCount, setUserCount] = useState<number | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      try {
        const tasks: Promise<void>[] = [];

        if (hasAnyPermission(["settings.manage"])) {
          tasks.push(
            getSettings()
              .then((res) => {
                if (!cancelled) {
                  setCompanyName(res.data.company_name ?? null);
                }
              })
              .catch(() => {
                if (!cancelled) setCompanyName(null);
              }),
          );
        }

        if (hasAnyPermission(["branches.view", "branches.manage"])) {
          tasks.push(
            listBranches(1)
              .then((res) => {
                if (!cancelled) {
                  setBranchCount(res.meta?.total ?? res.data.length);
                }
              })
              .catch(() => {
                if (!cancelled) setBranchCount(null);
              }),
          );
        }

        if (hasAnyPermission(["users.view", "users.manage"])) {
          tasks.push(
            listUsers(1)
              .then((res) => {
                if (!cancelled) {
                  setUserCount(res.meta?.total ?? res.data.length);
                }
              })
              .catch(() => {
                if (!cancelled) setUserCount(null);
              }),
          );
        }

        await Promise.all(tasks);
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [hasAnyPermission]);

  const stats = [
    {
      label: t("company"),
      value: companyName ?? t("unavailable"),
    },
    {
      label: t("branches"),
      value:
        branchCount === null ? t("unavailable") : String(branchCount),
    },
    {
      label: t("users"),
      value: userCount === null ? t("unavailable") : String(userCount),
    },
  ];

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {loading ? (
        <LoadingState label={tCommon("loading")} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-3">
          {stats.map((stat) => (
            <Panel key={stat.label} className="p-5">
              <p className="text-xs font-medium uppercase tracking-wider text-muted">
                {stat.label}
              </p>
              <p className="mt-3 text-2xl font-semibold text-primary">
                {stat.value}
              </p>
            </Panel>
          ))}
        </div>
      )}

      <Panel className="mt-4 border-s-4 border-s-sand p-5">
        <p className="text-sm text-foreground">{t("stageNote")}</p>
      </Panel>
    </div>
  );
}
