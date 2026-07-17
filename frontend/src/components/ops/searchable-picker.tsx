"use client";

import { useEffect, useId, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { Input } from "@/components/ui/form";
import { cn } from "@/lib/utils";
import type { PickerOption } from "@/lib/pickers";

export type SearchablePickerProps = {
  label: string;
  value?: number | string | null;
  selectedLabel?: string | null;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
  onChange: (option: PickerOption | null) => void;
  onSearch: (query: string) => Promise<PickerOption[]>;
  emptyLabel?: string;
};

export function SearchablePicker({
  label,
  value,
  selectedLabel,
  placeholder,
  disabled,
  className,
  onChange,
  onSearch,
  emptyLabel,
}: SearchablePickerProps) {
  const t = useTranslations("opsUi");
  const tCommon = useTranslations("common");
  const listId = useId();
  const inputId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [options, setOptions] = useState<PickerOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    const handle = window.setTimeout(() => {
      setLoading(true);
      setError(null);
      void onSearch(query)
        .then((rows) => {
          if (!cancelled) setOptions(rows);
        })
        .catch(() => {
          if (!cancelled) {
            setOptions([]);
            setError(tCommon("error"));
          }
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    }, 200);
    return () => {
      cancelled = true;
      window.clearTimeout(handle);
    };
  }, [open, query, onSearch, tCommon]);

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      if (!rootRef.current?.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, [open]);

  const display =
    selectedLabel ||
    (value != null && value !== "" ? String(value) : "") ||
    "";

  return (
    <div ref={rootRef} className={cn("relative", className)}>
      <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-foreground">
        {label}
      </label>
      <Input
        id={inputId}
        role="combobox"
        aria-expanded={open}
        aria-controls={listId}
        aria-autocomplete="list"
        disabled={disabled}
        value={open ? query : display}
        placeholder={placeholder ?? t("searchPlaceholder")}
        onFocus={() => {
          setOpen(true);
          setQuery("");
        }}
        onChange={(e) => {
          setOpen(true);
          setQuery(e.target.value);
        }}
        onKeyDown={(e) => {
          if (e.key === "Escape") setOpen(false);
          if (e.key === "Backspace" && !open && value != null) {
            onChange(null);
          }
        }}
      />
      {value != null && value !== "" && !disabled ? (
        <button
          type="button"
          className="absolute end-2 top-[2.15rem] text-xs text-muted hover:text-foreground"
          onClick={() => onChange(null)}
        >
          {t("clearSelection")}
        </button>
      ) : null}
      {open ? (
        <ul
          id={listId}
          role="listbox"
          className="absolute z-40 mt-1 max-h-56 w-full overflow-auto rounded-md border border-border bg-surface-elevated py-1 shadow-[var(--shadow)]"
        >
          {loading ? (
            <li className="px-3 py-2 text-sm text-muted">{tCommon("loading")}</li>
          ) : error ? (
            <li className="px-3 py-2 text-sm text-danger">{error}</li>
          ) : options.length === 0 ? (
            <li className="px-3 py-2 text-sm text-muted">{emptyLabel ?? t("noResults")}</li>
          ) : (
            options.map((opt) => (
              <li key={String(opt.id)} role="option" aria-selected={value === opt.id}>
                <button
                  type="button"
                  className={cn(
                    "flex w-full flex-col items-start px-3 py-2 text-start text-sm hover:bg-sand-soft",
                    value === opt.id && "bg-sand-soft",
                  )}
                  onClick={() => {
                    onChange(opt);
                    setOpen(false);
                    setQuery("");
                  }}
                >
                  <span className="font-medium text-foreground">{opt.label}</span>
                  {opt.description ? (
                    <span className="text-xs text-muted">{opt.description}</span>
                  ) : null}
                </button>
              </li>
            ))
          )}
        </ul>
      ) : null}
    </div>
  );
}
