"use client";

import { ConfirmDialog } from "@/components/ui/confirm-dialog";

/** Thin wrapper around ConfirmDialog for ops actions. */
export function ConfirmActionDialog({
  open,
  title,
  description,
  confirmLabel,
  danger,
  loading,
  onConfirm,
  onCancel,
}: {
  open: boolean;
  title: string;
  description: string;
  confirmLabel?: string;
  danger?: boolean;
  loading?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  return (
    <ConfirmDialog
      open={open}
      title={title}
      description={description}
      confirmLabel={confirmLabel}
      danger={danger}
      loading={loading}
      onConfirm={onConfirm}
      onCancel={onCancel}
    />
  );
}
