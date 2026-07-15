"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { login } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { useAuthStore } from "@/store/auth-store";
import { LocaleSwitcher } from "@/components/locale-switcher";
import { Button } from "@/components/ui/button";
import { FieldError, Input, Label } from "@/components/ui/form";
import { Alert } from "@/components/ui/layout";

export default function LoginPage() {
  const t = useTranslations("auth");
  const tApp = useTranslations("app");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const { token, user, hydrated } = useAuthStore();

  const [loginValue, setLoginValue] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!hydrated) return;
    if (token && user) {
      if (user.force_password_change) {
        router.replace("/change-password");
      } else {
        router.replace("/dashboard");
      }
    }
  }, [hydrated, token, user, router]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const data = await login(loginValue.trim(), password);
      if (data.force_password_change || data.user.force_password_change) {
        router.replace("/change-password");
      } else {
        router.replace("/dashboard");
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError(tCommon("error"));
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen flex-col">
      <div className="flex items-center justify-between px-4 py-4 sm:px-8">
        <p className="text-lg font-semibold tracking-tight text-primary">
          {tApp("name")}
        </p>
        <LocaleSwitcher />
      </div>

      <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 pb-16">
        <div className="mb-8">
          <h1 className="text-3xl font-semibold tracking-tight text-primary">
            {t("loginTitle")}
          </h1>
          <p className="mt-2 text-sm text-muted">{t("loginSubtitle")}</p>
        </div>

        <form
          onSubmit={onSubmit}
          className="space-y-4 rounded-lg border border-border bg-surface-elevated p-5 shadow-[var(--shadow)]"
        >
          {error ? <Alert>{error}</Alert> : null}

          <div>
            <Label htmlFor="login">{t("login")}</Label>
            <Input
              id="login"
              name="login"
              autoComplete="username"
              value={loginValue}
              onChange={(e) => setLoginValue(e.target.value)}
              required
            />
          </div>

          <div>
            <Label htmlFor="password">{t("password")}</Label>
            <Input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            <FieldError />
          </div>

          <Button type="submit" className="w-full" disabled={loading}>
            {loading ? t("signingIn") : t("signIn")}
          </Button>
        </form>
      </div>
    </div>
  );
}
