"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { TextArea } from "@/components/ui/form";
import { cn } from "@/lib/utils";

export type ActivityNoteType = "internal" | "customer_visible";

export function ActivityComposer({
  onSubmit,
  loading,
  className,
  defaultType = "internal",
}: {
  onSubmit: (payload: { type: ActivityNoteType; body: string }) => void | Promise<void>;
  loading?: boolean;
  className?: string;
  defaultType?: ActivityNoteType;
}) {
  const t = useTranslations("opsUi");
  const [type, setType] = useState<ActivityNoteType>(defaultType);
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit() {
    const trimmed = body.trim();
    if (!trimmed) return;
    setBusy(true);
    try {
      await onSubmit({ type, body: trimmed });
      setBody("");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className={cn("space-y-3", className)}>
      <div className="flex flex-wrap gap-2" role="group" aria-label={t("noteType")}>
        {(["internal", "customer_visible"] as const).map((k) => (
          <button
            key={k}
            type="button"
            aria-pressed={type === k}
            className={cn(
              "rounded-md border px-3 py-1.5 text-sm font-medium",
              type === k
                ? "border-primary bg-primary text-white"
                : "border-border bg-surface-elevated hover:bg-sand-soft",
            )}
            onClick={() => setType(k)}
          >
            {t(k === "internal" ? "noteInternal" : "noteCustomerVisible")}
          </button>
        ))}
      </div>
      <TextArea
        aria-label={t("noteBody")}
        value={body}
        onChange={(e) => setBody(e.target.value)}
        placeholder={t("notePlaceholder")}
        rows={4}
      />
      <div className="flex justify-end">
        <Button
          type="button"
          size="sm"
          disabled={loading || busy || !body.trim()}
          onClick={() => void submit()}
        >
          {t("submitNote")}
        </Button>
      </div>
    </div>
  );
}
