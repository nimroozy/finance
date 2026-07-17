import { apiFetch, toQuery } from "@/lib/api";

export type PickerOption = {
  id: number | string;
  label: string;
  description?: string;
  meta?: Record<string, unknown>;
};

export type PickerUser = {
  id: number;
  name: string;
  email?: string | null;
  username?: string | null;
  status?: string;
};

export type PickerCustomer = {
  id: number;
  branch_id?: number | null;
  customer_number?: string | null;
  contact_name?: string | null;
  company_name?: string | null;
  phone?: string | null;
  mobile?: string | null;
  status?: string;
};

export type Department = {
  id: number;
  code: string;
  name?: string | null;
  name_en?: string | null;
  name_fa?: string | null;
  branch_id?: number | null;
  is_central?: boolean;
};

export type Team = {
  id: number;
  code?: string | null;
  name?: string | null;
  name_en?: string | null;
  name_fa?: string | null;
  department_id?: number | null;
  branch_id?: number | null;
};

export type PickerBranch = {
  id: number;
  code: string;
  name_en: string;
  name_fa?: string | null;
};

function deptLabel(d: Department) {
  return d.name_en || d.name || d.name_fa || d.code || String(d.id);
}

function teamLabel(t: Team) {
  return t.name_en || t.name || t.name_fa || t.code || String(t.id);
}

/** Lightweight assignee search via `GET /pickers/users`. */
export async function searchUsers(
  q: string,
  opts?: { branchId?: number | string; departmentId?: number | string; limit?: number },
) {
  return apiFetch<PickerUser[]>(
    `/pickers/users${toQuery({
      q: q || undefined,
      branch_id: opts?.branchId,
      department_id: opts?.departmentId,
      limit: opts?.limit ?? 25,
    })}`,
  );
}

export async function searchUsersAsOptions(
  q: string,
  opts?: { branchId?: number | string; departmentId?: number | string },
) {
  const res = await searchUsers(q, opts);
  return {
    ...res,
    data: (res.data ?? []).map(
      (u): PickerOption => ({
        id: u.id,
        label: u.name,
        description: u.email || u.username || undefined,
        meta: { user: u },
      }),
    ),
  };
}

export async function listDepartments(branchId?: number | string) {
  return apiFetch<Department[]>(
    `/pickers/departments${toQuery({ branch_id: branchId })}`,
  );
}

export async function searchDepartments(q: string, branchId?: number | string) {
  const res = await listDepartments(branchId);
  const needle = q.trim().toLowerCase();
  const rows = (res.data ?? []).filter((d) => {
    if (!needle) return true;
    return `${deptLabel(d)} ${d.code}`.toLowerCase().includes(needle);
  });
  return {
    ...res,
    data: rows.map(
      (d): PickerOption => ({
        id: d.id,
        label: deptLabel(d),
        description: d.code,
        meta: { department: d },
      }),
    ),
  };
}

export async function searchTeams(
  q: string,
  opts?: { branchId?: number | string; departmentId?: number | string },
) {
  const res = await apiFetch<Team[]>(
    `/pickers/teams${toQuery({
      branch_id: opts?.branchId,
      department_id: opts?.departmentId,
    })}`,
  );
  const needle = q.trim().toLowerCase();
  const options = (res.data ?? [])
    .filter((t) => {
      if (!needle) return true;
      return `${teamLabel(t)} ${t.code ?? ""}`.toLowerCase().includes(needle);
    })
    .map(
      (t): PickerOption => ({
        id: t.id,
        label: teamLabel(t),
        description: t.code || undefined,
        meta: { team: t },
      }),
    );
  return { ...res, data: options };
}

export async function searchBranches(q: string) {
  const res = await apiFetch<PickerBranch[]>(`/pickers/branches`);
  const needle = q.trim().toLowerCase();
  const rows = (res.data ?? []).filter((b) => {
    if (!needle) return true;
    return `${b.code} ${b.name_en} ${b.name_fa ?? ""}`.toLowerCase().includes(needle);
  });
  return {
    ...res,
    data: rows.map(
      (b): PickerOption => ({
        id: b.id,
        label: b.name_en || b.name_fa || b.code,
        description: b.code,
        meta: { branch: b },
      }),
    ),
  };
}

export async function searchCustomers(q: string, branchId?: number | string) {
  const res = await apiFetch<PickerCustomer[]>(
    `/pickers/customers${toQuery({
      q: q || undefined,
      branch_id: branchId,
      limit: 25,
    })}`,
  );
  return {
    ...res,
    data: (res.data ?? []).map(
      (c): PickerOption => ({
        id: c.id,
        label: c.contact_name || c.company_name || c.customer_number || `#${c.id}`,
        description: [c.customer_number, c.mobile || c.phone].filter(Boolean).join(" · "),
        meta: { customer: c },
      }),
    ),
  };
}
