"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale } from "next-intl";
import { apiFetch } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Alert, LoadingState, PageHeader, Panel } from "@/components/ui/layout";

type Rule = {
  id: number;
  event_type: string;
  whatsapp_enabled: boolean;
  template_name?: string | null;
  language_code?: string;
  in_app_enabled?: boolean;
  is_active?: boolean;
};

export default function WhatsAppRulesPage() {
  const fa = useLocale() === "fa";
  const [rules, setRules] = useState<Rule[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch<Rule[]>("/whatsapp/rules");
      setRules(response.data);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => void load(), [load]);

  function updateRule(id: number, patch: Partial<Rule>) {
    setRules((rows) => rows.map((r) => (r.id === id ? { ...r, ...patch } : r)));
  }

  async function saveRule(rule: Rule) {
    setBusy(true);
    setNotice(null);
    try {
      await apiFetch("/whatsapp/rules", {
        method: "PUT",
        body: JSON.stringify({
          branch_id: null,
          event_type: rule.event_type,
          whatsapp_enabled: rule.whatsapp_enabled,
          template_name: rule.template_name,
          language_code: rule.language_code ?? "fa",
          in_app_enabled: rule.in_app_enabled ?? true,
          is_active: rule.is_active ?? true,
        }),
      });
      setNotice(fa ? "ذخیره شد" : "Saved");
      await load();
    } catch (e) {
      setNotice(e instanceof Error ? e.message : "Error");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader title={fa ? "قوانین اعلان واتساپ" : "WhatsApp notification rules"} subtitle="WhatsApp Cloud API" />
      {notice ? <Alert>{notice}</Alert> : null}
      {loading ? <LoadingState label={fa ? "بارگذاری…" : "Loading…"} /> : null}
      <div className="space-y-3">
        {rules.map((rule) => (
          <Panel key={rule.id} className="space-y-2 p-4 text-sm">
            <p className="font-medium">{rule.event_type}</p>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={rule.whatsapp_enabled}
                onChange={(e) => updateRule(rule.id, { whatsapp_enabled: e.target.checked })}
              />
              {fa ? "واتساپ فعال" : "WhatsApp enabled"}
            </label>
            <input
              className="w-full rounded border px-3 py-2"
              value={rule.template_name ?? ""}
              onChange={(e) => updateRule(rule.id, { template_name: e.target.value })}
              placeholder={fa ? "نام قالب" : "Template name"}
            />
            <Button disabled={busy} onClick={() => void saveRule(rule)}>
              {fa ? "ذخیره" : "Save"}
            </Button>
          </Panel>
        ))}
      </div>
    </div>
  );
}
