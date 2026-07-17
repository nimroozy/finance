"use client";

import { useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import { uploadAttachment as uploadOpsAttachment } from "@/lib/operations";
import {
  TICKET_PRIORITIES,
  createTicket,
  listTicketTypes,
  listTickets as listTicketsApi,
  type TicketType,
} from "@/lib/tickets";
import type { PickerCustomer, PickerOption } from "@/lib/pickers";
import { useAuthStore } from "@/store/auth-store";
import {
  AssignmentPicker,
  BranchPicker,
  CustomerPicker,
  CustomerSummaryCard,
  DepartmentPicker,
  WorkspaceHeader,
  type AssignmentValue,
} from "@/components/ops";
import { Button } from "@/components/ui/button";
import { Input, Select, TextArea } from "@/components/ui/form";
import { Alert, Panel } from "@/components/ui/layout";

export default function NewTicketPage() {
  const t = useTranslations("tickets");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const defaultBranch = useAuthStore((s) => s.user?.branches?.[0]);

  const [types, setTypes] = useState<TicketType[]>([]);
  const [branchId, setBranchId] = useState<string>(defaultBranch ? String(defaultBranch.id) : "");
  const [branchLabel, setBranchLabel] = useState<string | null>(
    defaultBranch?.name_en ?? null,
  );
  const [typeCode, setTypeCode] = useState("");
  const [subject, setSubject] = useState("");
  const [description, setDescription] = useState("");
  const [priority, setPriority] = useState("normal");
  const [impact, setImpact] = useState("");
  const [customer, setCustomer] = useState<PickerOption | null>(null);
  const [openTicketsWarning, setOpenTicketsWarning] = useState(0);
  const [department, setDepartment] = useState<PickerOption | null>(null);
  const [assignment, setAssignment] = useState<AssignmentValue>(null);
  const [files, setFiles] = useState<File[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void listTicketTypes()
      .then((res) => setTypes(res.data.filter((ty) => ty.is_active !== false)))
      .catch(() => setTypes([]));
  }, []);

  useEffect(() => {
    if (!customer) {
      setOpenTicketsWarning(0);
      return;
    }
    void listTicketsApi({
      customer_id: customer.id,
      per_page: 5,
      status: undefined,
    })
      .then((res) => {
        const open = res.data.filter(
          (row) => !["closed", "cancelled"].includes(row.status),
        );
        setOpenTicketsWarning(open.length);
      })
      .catch(() => setOpenTicketsWarning(0));
  }, [customer]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (busy) return;
    if (!branchId || !typeCode || !subject.trim()) {
      setError(t("requiredFields"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const payload: Parameters<typeof createTicket>[0] = {
        branch_id: Number(branchId),
        type_code: typeCode,
        subject: subject.trim(),
        description: description.trim() || undefined,
        priority,
        impact: impact || undefined,
        source: "manual",
        customer_id: customer ? Number(customer.id) : null,
      };
      if (department) payload.assigned_department_id = Number(department.id);
      if (assignment?.kind === "user") payload.primary_assignee_id = Number(assignment.id);
      if (assignment?.kind === "team") payload.assigned_team_id = Number(assignment.id);
      if (assignment?.kind === "department") {
        payload.assigned_department_id = Number(assignment.id);
      }

      const res = await createTicket(payload);
      if (files.length > 0) {
        for (const file of files) {
          await uploadOpsAttachment({
            file,
            attachable_type: "ticket",
            attachable_id: res.data.id,
          });
        }
      }
      router.push(`/tickets/${res.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
      setBusy(false);
    }
  }

  const customerMeta = customer?.meta?.customer as PickerCustomer | undefined;

  return (
    <div>
      <WorkspaceHeader title={t("newTitle")} subtitle={t("newSubtitle")} />
      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      <Panel className="p-4">
        <form className="grid max-w-2xl gap-4" onSubmit={(e) => void onSubmit(e)}>
          <BranchPicker
            value={branchId || null}
            selectedLabel={branchLabel}
            onChange={(opt) => {
              setBranchId(opt ? String(opt.id) : "");
              setBranchLabel(opt?.label ?? null);
            }}
          />

          <CustomerPicker
            branchId={branchId || undefined}
            value={customer?.id ?? null}
            selectedLabel={customer?.label}
            onChange={setCustomer}
          />

          {customer ? (
            <div className="space-y-2">
              <CustomerSummaryCard
                customerNumber={customerMeta?.customer_number}
                name={customer.label}
                phone={customerMeta?.mobile || customerMeta?.phone}
                openTicketsCount={openTicketsWarning}
              />
              {openTicketsWarning > 0 ? (
                <Alert tone="warning">{t("openTicketsWarning", { count: openTicketsWarning })}</Alert>
              ) : null}
            </div>
          ) : null}

          <label className="grid gap-1 text-sm">
            <span>{t("type")}</span>
            <Select value={typeCode} onChange={(e) => setTypeCode(e.target.value)} required>
              <option value="">{t("type")}</option>
              {types.map((ty) => (
                <option key={ty.code} value={ty.code}>
                  {locale === "fa" ? ty.name_fa || ty.name_en : ty.name_en}
                </option>
              ))}
            </Select>
          </label>

          <label className="grid gap-1 text-sm">
            <span>{t("subject")}</span>
            <Input value={subject} onChange={(e) => setSubject(e.target.value)} required />
          </label>

          <label className="grid gap-1 text-sm">
            <span>{t("description")}</span>
            <TextArea value={description} onChange={(e) => setDescription(e.target.value)} rows={4} />
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="grid gap-1 text-sm">
              <span>{t("priority")}</span>
              <Select value={priority} onChange={(e) => setPriority(e.target.value)}>
                {TICKET_PRIORITIES.map((p) => (
                  <option key={p} value={p}>
                    {t(`priority_${p}` as "priority_low")}
                  </option>
                ))}
              </Select>
            </label>
            <label className="grid gap-1 text-sm">
              <span>{t("impact")}</span>
              <Select value={impact} onChange={(e) => setImpact(e.target.value)}>
                <option value="">{t("impact")}</option>
                <option value="low">{t("priority_low")}</option>
                <option value="medium">{t("priority_medium")}</option>
                <option value="high">{t("priority_high")}</option>
                <option value="critical">{t("priority_critical")}</option>
              </Select>
            </label>
          </div>

          <DepartmentPicker
            branchId={branchId || undefined}
            value={department?.id ?? null}
            selectedLabel={department?.label}
            onChange={setDepartment}
          />

          <div>
            <p className="mb-2 text-sm font-medium">{t("assignment")}</p>
            <AssignmentPicker
              branchId={branchId || undefined}
              value={assignment}
              onChange={setAssignment}
            />
          </div>

          <label className="grid gap-1 text-sm">
            <span>{t("attachmentsOptional")}</span>
            <Input
              type="file"
              multiple
              onChange={(e) => setFiles(Array.from(e.target.files ?? []))}
            />
            {files.length > 0 ? (
              <p className="text-xs text-muted">{files.map((f) => f.name).join(", ")}</p>
            ) : null}
          </label>

          <div className="flex gap-2">
            <Button type="submit" disabled={busy}>
              {busy ? tCommon("loading") : tCommon("create")}
            </Button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
