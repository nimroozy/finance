"use client";

import { useCallback } from "react";
import { useTranslations } from "next-intl";
import { SearchablePicker } from "@/components/ops/searchable-picker";
import {
  searchBranches,
  searchCustomers,
  searchDepartments,
  searchTeams,
  searchUsersAsOptions,
  type PickerOption,
} from "@/lib/pickers";

type BaseProps = {
  value?: number | string | null;
  selectedLabel?: string | null;
  onChange: (option: PickerOption | null) => void;
  disabled?: boolean;
  className?: string;
  label?: string;
  branchId?: number | string;
};

export function UserPicker({ label, ...props }: BaseProps) {
  const t = useTranslations("opsUi");
  const onSearch = useCallback(async (q: string) => {
    const res = await searchUsersAsOptions(q);
    return res.data;
  }, []);
  return (
    <SearchablePicker
      {...props}
      label={label ?? t("user")}
      onSearch={onSearch}
    />
  );
}

export function TeamPicker({ label, branchId, ...props }: BaseProps) {
  const t = useTranslations("opsUi");
  const onSearch = useCallback(
    async (q: string) => {
      const res = await searchTeams(q, { branchId });
      return res.data;
    },
    [branchId],
  );
  return <SearchablePicker {...props} label={label ?? t("team")} onSearch={onSearch} />;
}

export function DepartmentPicker({ label, branchId, ...props }: BaseProps) {
  const t = useTranslations("opsUi");
  const onSearch = useCallback(
    async (q: string) => {
      const res = await searchDepartments(q, branchId);
      return res.data;
    },
    [branchId],
  );
  return (
    <SearchablePicker {...props} label={label ?? t("department")} onSearch={onSearch} />
  );
}

export function BranchPicker({ label, ...props }: BaseProps) {
  const t = useTranslations("opsUi");
  const onSearch = useCallback(async (q: string) => {
    const res = await searchBranches(q);
    return res.data;
  }, []);
  return <SearchablePicker {...props} label={label ?? t("branch")} onSearch={onSearch} />;
}

export function CustomerPicker({ label, branchId, ...props }: BaseProps) {
  const t = useTranslations("opsUi");
  const onSearch = useCallback(
    async (q: string) => {
      const res = await searchCustomers(q, branchId);
      return res.data;
    },
    [branchId],
  );
  return (
    <SearchablePicker {...props} label={label ?? t("customer")} onSearch={onSearch} />
  );
}
