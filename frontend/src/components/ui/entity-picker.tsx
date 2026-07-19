"use client";

import type { PickerOption } from "@/lib/pickers";
import { SearchableSelect } from "@/components/ui/searchable-select";

/**
 * Generic picker over any `/pickers/*` search function returning
 * PickerOption[]. Named entity pickers (CustomerPicker, BranchPicker, ...)
 * wrap this with a fixed `search` implementation.
 */
export function EntityPicker({
  value,
  onChange,
  search,
  placeholder,
  disabled,
  className,
}: {
  value: PickerOption | null;
  onChange: (option: PickerOption | null) => void;
  search: (query: string) => Promise<PickerOption[]>;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
}) {
  return (
    <SearchableSelect
      value={value}
      onChange={onChange}
      onSearch={search}
      placeholder={placeholder}
      disabled={disabled}
      className={className}
    />
  );
}
