"use client";

import { useRef } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { EmptyState, Panel } from "@/components/ui/layout";
import { cn } from "@/lib/utils";

export type AttachmentItem = {
  id: string;
  name: string;
  downloadUrl?: string | null;
  meta?: string;
};

export function AttachmentGallery({
  items,
  onUpload,
  onRefresh,
  uploading,
  className,
  accept,
  capture,
  uploadLabel,
}: {
  items: AttachmentItem[];
  onUpload?: (file: File) => void | Promise<void>;
  onRefresh?: () => void;
  uploading?: boolean;
  className?: string;
  /** e.g. "image/*" for damage photos */
  accept?: string;
  /** Prefer rear camera on mobile when capturing photos */
  capture?: boolean | "user" | "environment";
  uploadLabel?: string;
}) {
  const t = useTranslations("opsUi");
  const inputRef = useRef<HTMLInputElement>(null);

  return (
    <Panel className={cn("overflow-hidden", className)}>
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
        <h3 className="text-sm font-semibold text-foreground">{t("attachments")}</h3>
        <div className="flex flex-wrap gap-2">
          {onRefresh ? (
            <Button type="button" variant="secondary" size="sm" onClick={onRefresh}>
              {t("refresh")}
            </Button>
          ) : null}
          {onUpload ? (
            <>
              <input
                ref={inputRef}
                type="file"
                className="sr-only"
                aria-label={uploadLabel ?? t("upload")}
                accept={accept}
                capture={capture === true ? "environment" : capture || undefined}
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) void onUpload(file);
                  e.target.value = "";
                }}
              />
              <Button
                type="button"
                size="sm"
                disabled={uploading}
                onClick={() => inputRef.current?.click()}
              >
                {uploadLabel ?? t("upload")}
              </Button>
            </>
          ) : null}
        </div>
      </div>
      {items.length === 0 ? (
        <EmptyState label={t("noAttachments")} />
      ) : (
        <ul className="divide-y divide-border">
          {items.map((item) => (
            <li key={item.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
              <div className="min-w-0">
                <p className="truncate font-medium text-foreground">{item.name}</p>
                {item.meta ? <p className="text-xs text-muted">{item.meta}</p> : null}
              </div>
              {item.downloadUrl ? (
                <a
                  href={item.downloadUrl}
                  className="shrink-0 text-primary underline-offset-2 hover:underline"
                  target="_blank"
                  rel="noreferrer"
                >
                  {t("download")}
                </a>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}
