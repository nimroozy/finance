"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import {
  createUser,
  disableUser,
  listBranches,
  listRoles,
  listUsers,
} from "@/lib/auth";
import { ApiError } from "@/lib/api";
import type { AuthUser, Branch, Role } from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Modal } from "@/components/ui/modal";
import { FieldError, Input, Label, Select } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

const emptyForm = {
  name: "",
  email: "",
  username: "",
  password: "",
  locale: "en" as "en" | "fa",
  role: "",
  branch_ids: [] as number[],
};

export default function UsersPage() {
  const t = useTranslations("users");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const canManage = useAuthStore((s) => s.hasPermission("users.manage"));

  const [users, setUsers] = useState<AuthUser[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [saving, setSaving] = useState(false);
  const [roles, setRoles] = useState<Role[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);

  const [disableTarget, setDisableTarget] = useState<AuthUser | null>(null);
  const [disabling, setDisabling] = useState(false);

  const load = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const res = await listUsers(page);
      setUsers(res.data);
      setMeta({
        current_page: res.meta?.current_page ?? 1,
        last_page: res.meta?.last_page ?? 1,
      });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [tCommon]);

  useEffect(() => {
    void load(1);
  }, [load]);

  async function openCreate() {
    setForm({ ...emptyForm, locale: locale === "fa" ? "fa" : "en" });
    setFormError(null);
    setFieldErrors({});
    setModalOpen(true);
    try {
      const [rolesRes, branchesRes] = await Promise.all([
        listRoles().catch(() => ({ data: [] as Role[] })),
        listBranches(1).catch(() => ({ data: [] as Branch[] })),
      ]);
      setRoles(rolesRes.data);
      setBranches(branchesRes.data);
      if (rolesRes.data[0]) {
        setForm((prev) => ({ ...prev, role: rolesRes.data[0].name }));
      }
    } catch {
      // optional lookups
    }
  }

  async function onCreate(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setFormError(null);
    setFieldErrors({});
    try {
      await createUser({
        name: form.name.trim(),
        email: form.email.trim(),
        username: form.username.trim() || undefined,
        password: form.password,
        locale: form.locale,
        roles: form.role ? [form.role] : [],
        branch_ids: form.branch_ids,
        force_password_change: true,
      });
      setModalOpen(false);
      setSuccess(t("createSuccess"));
      await load(meta.current_page);
    } catch (err) {
      if (err instanceof ApiError) {
        setFormError(err.message);
        setFieldErrors(err.errors);
      } else {
        setFormError(tCommon("error"));
      }
    } finally {
      setSaving(false);
    }
  }

  async function confirmDisable() {
    if (!disableTarget) return;
    setDisabling(true);
    try {
      await disableUser(disableTarget.id);
      setDisableTarget(null);
      setSuccess(t("disableSuccess"));
      await load(meta.current_page);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setDisableTarget(null);
    } finally {
      setDisabling(false);
    }
  }

  function toggleBranch(id: number) {
    setForm((prev) => ({
      ...prev,
      branch_ids: prev.branch_ids.includes(id)
        ? prev.branch_ids.filter((b) => b !== id)
        : [...prev.branch_ids, id],
    }));
  }

  return (
    <div>
      <PageHeader
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          canManage ? (
            <Button onClick={() => void openCreate()}>{t("create")}</Button>
          ) : undefined
        }
      />

      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel>
        {loading ? (
          <LoadingState label={tCommon("loading")} />
        ) : users.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-start text-sm">
              <thead className="border-b border-border bg-sand-soft/40 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">{t("name")}</th>
                  <th className="px-4 py-3 font-medium">{t("email")}</th>
                  <th className="px-4 py-3 font-medium">{t("roles")}</th>
                  <th className="px-4 py-3 font-medium">{t("status")}</th>
                  {canManage ? (
                    <th className="px-4 py-3 font-medium">{tCommon("actions")}</th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {users.map((user) => (
                  <tr key={user.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3">
                      <div className="font-medium">{user.name}</div>
                      <div className="text-xs text-muted">{user.username}</div>
                    </td>
                    <td className="px-4 py-3">{user.email}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-1">
                        {user.roles.map((role) => (
                          <Badge key={role} tone="info">
                            {role}
                          </Badge>
                        ))}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <Badge
                        tone={user.status === "active" ? "success" : "danger"}
                      >
                        {user.status === "active"
                          ? tCommon("active")
                          : tCommon("disabled")}
                      </Badge>
                    </td>
                    {canManage ? (
                      <td className="px-4 py-3">
                        {user.status === "active" ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDisableTarget(user)}
                          >
                            {tCommon("disable")}
                          </Button>
                        ) : (
                          "—"
                        )}
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta.last_page > 1 ? (
          <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
            <p className="text-sm text-muted">
              {tCommon("page", {
                page: meta.current_page,
                total: meta.last_page,
              })}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page <= 1 || loading}
                onClick={() => void load(meta.current_page - 1)}
              >
                {tCommon("previous")}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page >= meta.last_page || loading}
                onClick={() => void load(meta.current_page + 1)}
              >
                {tCommon("next")}
              </Button>
            </div>
          </div>
        ) : null}
      </Panel>

      <Modal
        open={modalOpen}
        title={t("create")}
        onClose={() => setModalOpen(false)}
        wide
      >
        <form onSubmit={onCreate} className="space-y-4">
          {formError ? <Alert>{formError}</Alert> : null}
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="name">{t("name")}</Label>
              <Input
                id="name"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
              />
              <FieldError>{fieldErrors.name?.[0]}</FieldError>
            </div>
            <div>
              <Label htmlFor="email">{t("email")}</Label>
              <Input
                id="email"
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                required
              />
              <FieldError>{fieldErrors.email?.[0]}</FieldError>
            </div>
            <div>
              <Label htmlFor="username">{t("username")}</Label>
              <Input
                id="username"
                value={form.username}
                onChange={(e) =>
                  setForm({ ...form, username: e.target.value })
                }
              />
              <FieldError>{fieldErrors.username?.[0]}</FieldError>
            </div>
            <div>
              <Label htmlFor="password">{t("password")}</Label>
              <Input
                id="password"
                type="password"
                value={form.password}
                onChange={(e) =>
                  setForm({ ...form, password: e.target.value })
                }
                required
                minLength={12}
              />
              <FieldError>{fieldErrors.password?.[0]}</FieldError>
            </div>
            <div>
              <Label htmlFor="locale">{t("locale")}</Label>
              <Select
                id="locale"
                value={form.locale}
                onChange={(e) =>
                  setForm({
                    ...form,
                    locale: e.target.value as "en" | "fa",
                  })
                }
              >
                <option value="en">English</option>
                <option value="fa">فارسی</option>
              </Select>
            </div>
            <div>
              <Label htmlFor="role">{t("roles")}</Label>
              <Select
                id="role"
                value={form.role}
                onChange={(e) => setForm({ ...form, role: e.target.value })}
              >
                <option value="">—</option>
                {roles.map((role) => (
                  <option key={role.id} value={role.name}>
                    {role.name}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          <div>
            <Label>{t("branches")}</Label>
            <div className="mt-1 max-h-40 space-y-2 overflow-y-auto rounded-md border border-border p-3">
              {branches.length === 0 ? (
                <p className="text-sm text-muted">{tCommon("empty")}</p>
              ) : (
                branches.map((branch) => (
                  <label
                    key={branch.id}
                    className="flex items-center gap-2 text-sm"
                  >
                    <input
                      type="checkbox"
                      checked={form.branch_ids.includes(branch.id)}
                      onChange={() => toggleBranch(branch.id)}
                    />
                    <span>
                      {branch.code} —{" "}
                      {locale === "fa" ? branch.name_fa : branch.name_en}
                    </span>
                  </label>
                ))
              )}
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button
              variant="secondary"
              type="button"
              onClick={() => setModalOpen(false)}
            >
              {tCommon("cancel")}
            </Button>
            <Button type="submit" disabled={saving}>
              {tCommon("create")}
            </Button>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(disableTarget)}
        title={tCommon("disable")}
        description={t("disableConfirm", {
          name: disableTarget?.name ?? "",
        })}
        confirmLabel={tCommon("disable")}
        danger
        loading={disabling}
        onCancel={() => setDisableTarget(null)}
        onConfirm={() => void confirmDisable()}
      />
    </div>
  );
}
