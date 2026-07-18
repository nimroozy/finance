"use client";

import type { PickerOption } from "@/lib/pickers";
import {
  searchBranches,
  searchCollectors,
  searchCustomers,
  searchUsersAsOptions,
} from "@/lib/pickers";
import { EntityPicker } from "@/components/ui/entity-picker";
import { useTranslations } from "next-intl";

type PickerProps = {
  value: PickerOption | null;
  onChange: (option: PickerOption | null) => void;
  disabled?: boolean;
  className?: string;
};

/** Search customers by name, company, customer number, phone, WhatsApp, or Zoho contact ID. */
export function CustomerPicker({
  value,
  onChange,
  branchId,
  disabled,
  className,
}: PickerProps & { branchId?: number | string }) {
  const t = useTranslations("pickers");
  return (
    <EntityPicker
      value={value}
      onChange={onChange}
      search={async (q) => (await searchCustomers(q, branchId)).data}
      placeholder={t("customerPlaceholder")}
      disabled={disabled}
      className={className}
    />
  );
}

/** Search staff holding the Collector role, optionally scoped to a branch. */
export function CollectorPicker({
  value,
  onChange,
  branchId,
  disabled,
  className,
}: PickerProps & { branchId?: number | string }) {
  const t = useTranslations("pickers");
  return (
    <EntityPicker
      value={value}
      onChange={onChange}
      search={async (q) => (await searchCollectors(q, branchId)).data}
      placeholder={t("collectorPlaceholder")}
      disabled={disabled}
      className={className}
    />
  );
}

/** Search any active staff user (assignees, task owners, etc.). */
export function UserPicker({
  value,
  onChange,
  branchId,
  disabled,
  className,
}: PickerProps & { branchId?: number | string }) {
  const t = useTranslations("pickers");
  return (
    <EntityPicker
      value={value}
      onChange={onChange}
      search={async (q) => (await searchUsersAsOptions(q, { branchId })).data}
      placeholder={t("userPlaceholder")}
      disabled={disabled}
      className={className}
    />
  );
}

/** Search branches by code or name. */
export function BranchPicker({ value, onChange, disabled, className }: PickerProps) {
  const t = useTranslations("pickers");
  return (
    <EntityPicker
      value={value}
      onChange={onChange}
      search={async (q) => (await searchBranches(q)).data}
      placeholder={t("branchPlaceholder")}
      disabled={disabled}
      className={className}
    />
  );
}

/**
 * Placeholder entity pickers for domains not in scope this pass (Stage 9
 * Inventory / Stage 10 Services). The component identity exists now so
 * later stages only need to supply a `search` implementation.
 */
export function ServicePicker(props: PickerProps & { search: (q: string) => Promise<PickerOption[]> }) {
  const t = useTranslations("pickers");
  return <EntityPicker {...props} placeholder={t("servicePlaceholder")} />;
}

export function ProductPicker(props: PickerProps & { search: (q: string) => Promise<PickerOption[]> }) {
  const t = useTranslations("pickers");
  return <EntityPicker {...props} placeholder={t("productPlaceholder")} />;
}

export function EquipmentPicker(props: PickerProps & { search: (q: string) => Promise<PickerOption[]> }) {
  const t = useTranslations("pickers");
  return <EntityPicker {...props} placeholder={t("equipmentPlaceholder")} />;
}
