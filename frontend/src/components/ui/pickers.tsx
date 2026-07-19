"use client";

import type { PickerOption } from "@/lib/pickers";
import {
  searchBranches,
  searchCollectors,
  searchCustomers,
  searchEquipment,
  searchProducts,
  searchServices,
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

/** Search services by number, or by customer name/company. */
export function ServicePicker({
  value,
  onChange,
  branchId,
  customerId,
  disabled,
  className,
}: PickerProps & { branchId?: number | string; customerId?: number | string }) {
  const t = useTranslations("pickers");
  return (
    <EntityPicker
      value={value}
      onChange={onChange}
      search={async (q) => (await searchServices(q, { branchId, customerId })).data}
      placeholder={t("servicePlaceholder")}
      disabled={disabled}
      className={className}
    />
  );
}

/** Search active products by code or name. */
export function ProductPicker({ value, onChange, disabled, className }: PickerProps) {
  const t = useTranslations("pickers");
  return (
    <EntityPicker
      value={value}
      onChange={onChange}
      search={async (q) => (await searchProducts(q)).data}
      placeholder={t("productPlaceholder")}
      disabled={disabled}
      className={className}
    />
  );
}

/** Search serialized equipment by serial number, MAC address, or equipment number. */
export function EquipmentPicker({
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
      search={async (q) => (await searchEquipment(q, branchId)).data}
      placeholder={t("equipmentPlaceholder")}
      disabled={disabled}
      className={className}
    />
  );
}
