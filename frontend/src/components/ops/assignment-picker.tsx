"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import {
  UserPicker,
  TeamPicker,
  DepartmentPicker,
} from "@/components/ops/entity-pickers";
import type { PickerOption } from "@/lib/pickers";
import { cn } from "@/lib/utils";

export type AssignmentTargetKind = "user" | "team" | "department";

export type AssignmentValue = {
  kind: AssignmentTargetKind;
  id: number | string;
  label: string;
} | null;

export function AssignmentPicker({
  value,
  onChange,
  branchId,
  className,
  allowedKinds = ["user", "team", "department"],
}: {
  value: AssignmentValue;
  onChange: (next: AssignmentValue) => void;
  branchId?: number | string;
  className?: string;
  allowedKinds?: AssignmentTargetKind[];
}) {
  const t = useTranslations("opsUi");
  const [kind, setKind] = useState<AssignmentTargetKind>(
    value?.kind ?? allowedKinds[0] ?? "user",
  );

  function handlePick(option: PickerOption | null) {
    if (!option) {
      onChange(null);
      return;
    }
    onChange({ kind, id: option.id, label: option.label });
  }

  return (
    <div className={cn("space-y-3", className)}>
      <div className="flex flex-wrap gap-2" role="group" aria-label={t("assignmentTarget")}>
        {allowedKinds.map((k) => (
          <button
            key={k}
            type="button"
            aria-pressed={kind === k}
            className={cn(
              "rounded-md border px-3 py-1.5 text-sm font-medium",
              kind === k
                ? "border-primary bg-primary text-white"
                : "border-border bg-surface-elevated hover:bg-sand-soft",
            )}
            onClick={() => {
              setKind(k);
              onChange(null);
            }}
          >
            {t(k)}
          </button>
        ))}
      </div>
      {kind === "user" ? (
        <UserPicker
          value={value?.kind === "user" ? value.id : null}
          selectedLabel={value?.kind === "user" ? value.label : null}
          onChange={handlePick}
        />
      ) : null}
      {kind === "team" ? (
        <TeamPicker
          branchId={branchId}
          value={value?.kind === "team" ? value.id : null}
          selectedLabel={value?.kind === "team" ? value.label : null}
          onChange={handlePick}
        />
      ) : null}
      {kind === "department" ? (
        <DepartmentPicker
          branchId={branchId}
          value={value?.kind === "department" ? value.id : null}
          selectedLabel={value?.kind === "department" ? value.label : null}
          onChange={handlePick}
        />
      ) : null}
    </div>
  );
}
