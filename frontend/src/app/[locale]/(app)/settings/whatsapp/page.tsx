"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { apiFetch } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Alert, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

type Status = {
  connected: boolean;
  status: string;
  paused: boolean;
  phone_number_id?: string;
  business_account_id?: string;
  api_version?: string;
  token_masked?: string;
  last_error?: string;
};

export default function WhatsAppSettingsPage() {
  const fa = useLocale() === "fa";
  const [status, setStatus] = useState<Status | null>(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [testPhone, setTestPhone] = useState("");
  const [testTemplate, setTestTemplate] = useState("payment_received");
  const [testLanguage, setTestLanguage] = useState("fa");

  const load = useCallback(async () => {
    const response = await apiFetch<Status>("/whatsapp/status");
    setStatus(response.data);
  }, []);

  useEffect(() => void load(), [load]);

  async function action(path: string) {
    setBusy(true);
    setNotice(null);
    try {
      await apiFetch(path, { method: "POST" });
      setNotice(fa ? "عملیات انجام شد" : "Action completed");
      await load();
    } catch (e) {
      setNotice(e instanceof Error ? e.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  async function sendTest() {
    setBusy(true);
    setNotice(null);
    try {
      await apiFetch("/whatsapp/send-test", {
        method: "POST",
        body: JSON.stringify({
          phone: testPhone,
          template_name: testTemplate,
          language_code: testLanguage,
        }),
      });
      setNotice(fa ? "پیام آزمایشی در صف قرار گرفت" : "Test message queued");
    } catch (e) {
      setNotice(e instanceof Error ? e.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader title={fa ? "تنظیمات واتساپ" : "WhatsApp settings"} subtitle="WhatsApp Cloud API" />
      {notice ? <Alert>{notice}</Alert> : null}
      {!status ? (
        <LoadingState label={fa ? "بارگذاری…" : "Loading…"} />
      ) : (
        <Panel className="space-y-3 p-5 text-sm">
          <p><strong>{fa ? "وضعیت" : "Status"}:</strong> {status.status}</p>
          <p><strong>{fa ? "شناسه شماره" : "Phone number ID"}:</strong> {status.phone_number_id || "—"}</p>
          <p><strong>{fa ? "شناسه کسب‌وکار" : "Business account ID"}:</strong> {status.business_account_id || "—"}</p>
          <p><strong>{fa ? "نسخه API" : "API version"}:</strong> {status.api_version || "—"}</p>
          <p><strong>{fa ? "توکن" : "Token"}:</strong> {status.token_masked || "—"}</p>
          <p><strong>{fa ? "ارسال" : "Outgoing"}:</strong> {status.paused ? (fa ? "متوقف" : "Paused") : (fa ? "فعال" : "Active")}</p>
          {status.last_error ? <Alert tone="danger">{status.last_error}</Alert> : null}
          <div className="flex flex-wrap gap-2">
            <Button disabled={busy} onClick={() => void action("/whatsapp/test-connection")}>
              {fa ? "آزمایش اتصال" : "Test connection"}
            </Button>
            <Button disabled={busy} variant="secondary" onClick={() => void action("/whatsapp/templates/sync")}>
              {fa ? "همگام‌سازی قالب‌ها" : "Sync templates"}
            </Button>
            <Button disabled={busy} variant="secondary" onClick={() => void action(status.paused ? "/whatsapp/resume" : "/whatsapp/pause")}>
              {status.paused ? (fa ? "ادامه" : "Resume") : (fa ? "توقف" : "Pause")}
            </Button>
          </div>
          <div className="mt-4 space-y-2 border-t pt-4">
            <p className="font-medium">{fa ? "ارسال آزمایشی" : "Send test"}</p>
            <input className="w-full rounded border px-3 py-2" placeholder={fa ? "شماره" : "Phone"} value={testPhone} onChange={(e) => setTestPhone(e.target.value)} />
            <input className="w-full rounded border px-3 py-2" placeholder={fa ? "نام قالب" : "Template name"} value={testTemplate} onChange={(e) => setTestTemplate(e.target.value)} />
            <input className="w-full rounded border px-3 py-2" placeholder={fa ? "زبان" : "Language"} value={testLanguage} onChange={(e) => setTestLanguage(e.target.value)} />
            <Button disabled={busy || !testPhone} onClick={() => void sendTest()}>
              {fa ? "ارسال آزمایشی" : "Send test"}
            </Button>
          </div>
        </Panel>
      )}
    </div>
  );
}
