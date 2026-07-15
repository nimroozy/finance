"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { listRoles } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import type { Role } from "@/lib/types";
import { Badge } from "@/components/ui/badge";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function RolesPage() {
  const t = useTranslations("roles");
  const tCommon = useTranslations("common");
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    async function load() {
      setLoading(true);
      try {
        const res = await listRoles();
        if (!cancelled) setRoles(res.data);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : tCommon("error"));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, [tCommon]);

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}

      {loading ? (
        <LoadingState label={tCommon("loading")} />
      ) : roles.length === 0 ? (
        <Panel>
          <EmptyState label={t("empty")} />
        </Panel>
      ) : (
        <div className="space-y-4">
          {roles.map((role) => (
            <Panel key={role.id} className="p-5">
              <h2 className="text-base font-semibold text-primary">
                {role.name}
              </h2>
              <p className="mt-1 text-xs uppercase tracking-wider text-muted">
                {t("permissions")}
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                {role.permissions.length === 0 ? (
                  <span className="text-sm text-muted">—</span>
                ) : (
                  role.permissions.map((permission) => (
                    <Badge key={permission} tone="info">
                      {permission}
                    </Badge>
                  ))
                )}
              </div>
            </Panel>
          ))}
        </div>
      )}
    </div>
  );
}
