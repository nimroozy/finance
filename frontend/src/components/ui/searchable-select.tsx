"use client";

import { useEffect, useId, useRef, useState } from "react";
import { Check, ChevronDown, Loader2, Search, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";

export type SearchableOption = {
  id: number | string;
  label: string;
  description?: string;
};

type SearchableSelectProps<T extends SearchableOption> = {
  value: T | null;
  onChange: (option: T | null) => void;
  /** Called on every debounced keystroke (including "" on open) to fetch options. */
  onSearch: (query: string) => Promise<T[]>;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
  /** Minimum characters before searching. 0 = search immediately (small lists). */
  minChars?: number;
};

/**
 * Generic async combobox: type to search, pick from results. Never asks the
 * user to type a raw numeric ID — every entity picker in the app is built on
 * this.
 */
export function SearchableSelect<T extends SearchableOption>({
  value,
  onChange,
  onSearch,
  placeholder,
  disabled,
  className,
  minChars = 0,
}: SearchableSelectProps<T>) {
  const t = useTranslations("common");
  const listboxId = useId();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [options, setOptions] = useState<T[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const [highlighted, setHighlighted] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    function onClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener("mousedown", onClickOutside);
    return () => document.removeEventListener("mousedown", onClickOutside);
  }, []);

  function runSearch(q: string) {
    if (q.trim().length < minChars) {
      setOptions([]);
      return;
    }
    setLoading(true);
    setError(false);
    onSearch(q)
      .then((results) => setOptions(results))
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }

  function onQueryChange(next: string) {
    setQuery(next);
    setHighlighted(0);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => runSearch(next), 250);
  }

  function openList() {
    if (disabled) return;
    setOpen(true);
    if (options.length === 0) runSearch(query);
  }

  function selectOption(option: T) {
    onChange(option);
    setOpen(false);
    setQuery("");
  }

  function onKeyDown(event: React.KeyboardEvent) {
    if (!open) {
      if (event.key === "ArrowDown" || event.key === "Enter") {
        event.preventDefault();
        openList();
      }
      return;
    }
    if (event.key === "ArrowDown") {
      event.preventDefault();
      setHighlighted((i) => Math.min(i + 1, options.length - 1));
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      setHighlighted((i) => Math.max(i - 1, 0));
    } else if (event.key === "Enter") {
      event.preventDefault();
      const option = options[highlighted];
      if (option) selectOption(option);
    } else if (event.key === "Escape") {
      setOpen(false);
    }
  }

  return (
    <div ref={containerRef} className={cn("relative", className)}>
      {value && !open ? (
        <button
          type="button"
          disabled={disabled}
          onClick={openList}
          className="flex h-10 w-full items-center justify-between gap-2 rounded-md border border-border bg-surface-elevated px-3 text-sm transition-colors hover:border-border-strong disabled:opacity-60"
        >
          <span className="min-w-0 truncate text-start">
            <span className="font-medium text-foreground">{value.label}</span>
            {value.description ? (
              <span className="ms-1.5 text-muted">{value.description}</span>
            ) : null}
          </span>
          <span className="flex shrink-0 items-center gap-1">
            <span
              role="button"
              tabIndex={-1}
              aria-label={t("reset")}
              onClick={(event) => {
                event.stopPropagation();
                onChange(null);
                setQuery("");
              }}
              className="rounded p-0.5 text-muted hover:text-foreground"
            >
              <X className="h-3.5 w-3.5" />
            </span>
            <ChevronDown className="h-3.5 w-3.5 text-muted" aria-hidden />
          </span>
        </button>
      ) : (
        <div className="relative">
          <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-muted" aria-hidden />
          <input
            type="text"
            role="combobox"
            aria-expanded={open}
            aria-controls={listboxId}
            aria-autocomplete="list"
            disabled={disabled}
            value={query}
            placeholder={placeholder ?? t("search")}
            onFocus={openList}
            onChange={(event) => onQueryChange(event.target.value)}
            onKeyDown={onKeyDown}
            className="h-10 w-full rounded-md border border-border bg-surface-elevated ps-9 pe-3 text-sm text-foreground placeholder:text-muted/80 transition-colors hover:border-border-strong focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60"
          />
        </div>
      )}

      {open ? (
        <div className="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-surface-elevated shadow-[var(--shadow-lg)]">
          {loading ? (
            <div className="flex items-center gap-2 px-3 py-3 text-sm text-muted">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
              {t("loading")}
            </div>
          ) : error ? (
            <div className="px-3 py-3 text-sm text-danger">{t("error")}</div>
          ) : options.length === 0 ? (
            <div className="px-3 py-3 text-sm text-muted">{t("empty")}</div>
          ) : (
            <ul id={listboxId} role="listbox">
              {options.map((option, index) => (
                <li key={option.id} role="option" aria-selected={value?.id === option.id}>
                  <button
                    type="button"
                    onMouseEnter={() => setHighlighted(index)}
                    onClick={() => selectOption(option)}
                    className={cn(
                      "flex w-full items-center justify-between gap-2 px-3 py-2 text-start text-sm",
                      index === highlighted ? "bg-primary/10" : "hover:bg-surface-muted",
                    )}
                  >
                    <span className="min-w-0 truncate">
                      <span className="font-medium text-foreground">{option.label}</span>
                      {option.description ? (
                        <span className="ms-1.5 text-muted">{option.description}</span>
                      ) : null}
                    </span>
                    {value?.id === option.id ? (
                      <Check className="h-3.5 w-3.5 shrink-0 text-primary" aria-hidden />
                    ) : null}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      ) : null}
    </div>
  );
}
