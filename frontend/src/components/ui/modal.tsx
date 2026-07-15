"use client";

import { useEffect } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";

interface ModalProps {
  open: boolean;
  title: string;
  children: React.ReactNode;
  onClose: () => void;
  wide?: boolean;
}

export function Modal({ open, title, children, onClose, wide }: ModalProps) {
  const t = useTranslations("nav");

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-primary/40 p-4 sm:items-center"
      role="presentation"
      onClick={onClose}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        className={`max-h-[90vh] w-full overflow-y-auto rounded-lg border border-border bg-surface-elevated shadow-[var(--shadow)] ${
          wide ? "max-w-2xl" : "max-w-lg"
        }`}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between border-b border-border px-5 py-4">
          <h2 id="modal-title" className="text-lg font-semibold">
            {title}
          </h2>
          <Button variant="ghost" size="sm" onClick={onClose} aria-label={t("close")}>
            ✕
          </Button>
        </div>
        <div className="p-5">{children}</div>
      </div>
    </div>
  );
}
