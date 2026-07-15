"use client";

import { useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { changePassword } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { useAuthStore } from "@/store/auth-store";
import { Button } from "@/components/ui/button";
import { FieldError, Input, Label } from "@/components/ui/form";
import {
  Alert,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

function passwordHints(password: string) {
  return {
    length: password.length >= 12,
    case: /[a-z]/.test(password) && /[A-Z]/.test(password),
    number: /\d/.test(password),
    symbol: /[^A-Za-z0-9]/.test(password),
  };
}

export default function ChangePasswordPage() {
  const t = useTranslations("changePassword");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const setUser = useAuthStore((s) => s.setUser);

  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [success, setSuccess] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const hints = useMemo(() => passwordHints(password), [password]);
  const forced = Boolean(user?.force_password_change);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSuccess(null);
    setFieldErrors({});

    if (password !== confirmation) {
      setFieldErrors({
        password: ["Passwords do not match."],
      });
      return;
    }

    setSaving(true);
    try {
      await changePassword({
        current_password: currentPassword,
        password,
        password_confirmation: confirmation,
      });

      if (user) {
        setUser({ ...user, force_password_change: false });
      }

      setSuccess(t("success"));
      setCurrentPassword("");
      setPassword("");
      setConfirmation("");

      if (forced) {
        setTimeout(() => router.replace("/dashboard"), 600);
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError(tCommon("error"));
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader title={t("title")} subtitle={t("subtitle")} />

      {forced ? (
        <div className="mb-4">
          <Alert tone="warning">{t("forcedNote")}</Alert>
        </div>
      ) : null}
      {error ? <div className="mb-4"><Alert>{error}</Alert></div> : null}
      {success ? (
        <div className="mb-4">
          <Alert tone="success">{success}</Alert>
        </div>
      ) : null}

      <Panel className="p-5">
        <form onSubmit={onSubmit} className="mx-auto max-w-lg space-y-4">
          <div>
            <Label htmlFor="current_password">{t("currentPassword")}</Label>
            <Input
              id="current_password"
              type="password"
              autoComplete="current-password"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              required
            />
            <FieldError>{fieldErrors.current_password?.[0]}</FieldError>
          </div>
          <div>
            <Label htmlFor="password">{t("newPassword")}</Label>
            <Input
              id="password"
              type="password"
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            <FieldError>{fieldErrors.password?.[0]}</FieldError>
          </div>
          <div>
            <Label htmlFor="password_confirmation">{t("confirmPassword")}</Label>
            <Input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              value={confirmation}
              onChange={(e) => setConfirmation(e.target.value)}
              required
            />
          </div>

          <div className="rounded-md border border-border bg-sand-soft/30 p-3">
            <p className="text-sm font-medium text-foreground">
              {t("hintsTitle")}
            </p>
            <ul className="mt-2 space-y-1 text-sm">
              {(
                [
                  ["length", t("hintLength")],
                  ["case", t("hintCase")],
                  ["number", t("hintNumber")],
                  ["symbol", t("hintSymbol")],
                ] as const
              ).map(([key, label]) => (
                <li
                  key={key}
                  className={hints[key] ? "text-success" : "text-muted"}
                >
                  {hints[key] ? "✓" : "○"} {label}
                </li>
              ))}
            </ul>
          </div>

          <Button type="submit" disabled={saving}>
            {t("submit")}
          </Button>
        </form>
      </Panel>
    </div>
  );
}
