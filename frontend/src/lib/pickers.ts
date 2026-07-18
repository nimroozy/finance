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
  whatsapp_number?: string | null;
  zoho_contact_id?: string | null;
  status?: string;
  sync_status?: string | null;
  branch?: { id: number; code: string; name_en: string; name_fa?: string | null } | null;
};

export type PickerCollector = {
  id: number;
  user_id: number;
  employee_code?: string | null;
  is_active: boolean;
  active_assignments_count?: number;
  user?: {
    id: number;
    name: string;
    status?: string;
    branches?: Array<{ id: number; code: string; name_en: string; name_fa?: string | null }>;
  } | null;
};

export type PickerService = {
  id: number;
  service_number: string;
  customer_id: number;
  branch_id: number;
  package_id?: number | null;
  commercial_status: string;
  operational_status: string;
  customer?: { id: number; contact_name?: string | null; company_name?: string | null } | null;
  package?: { id: number; name: string } | null;
};

export type PickerEquipment = {
  id: number;
  equipment_number?: string | null;
  product_id?: number | null;
  serial_number?: string | null;
  mac_address?: string | null;
  branch_id: number;
  location_id?: number | null;
  custodian_user_id?: number | null;
  status?: string | null;
  product?: { id: number; code: string; name_en: string } | null;
  location?: { id: number; name: string } | null;
  custodian?: { id: number; name: string } | null;
};

export type PickerProduct = {
  id: number;
  code: string;
  name_en: string;
  name_fa?: string | null;
  category_id?: number | null;
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
  is_active?: boolean;
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
  opts?: { branchId?: number | string; departmentId?: number | string; role?: string; limit?: number },
) {
  return apiFetch<PickerUser[]>(
    `/pickers/users${toQuery({
      q: q || undefined,
      branch_id: opts?.branchId,
      department_id: opts?.departmentId,
      role: opts?.role,
      limit: opts?.limit ?? 25,
    })}`,
  );
}

export async function searchUsersAsOptions(
  q: string,
  opts?: { branchId?: number | string; departmentId?: number | string; role?: string },
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
        description: b.is_active === false ? `${b.code} · inactive` : b.code,
        meta: { branch: b },
      }),
    ),
  };
}

/** Collectors — used by CollectorPicker (assignments, ownership transfer, handovers). */
export async function searchCollectors(q: string, branchId?: number | string) {
  const res = await apiFetch<PickerCollector[]>(
    `/pickers/collectors${toQuery({ q: q || undefined, branch_id: branchId, limit: 25 })}`,
  );
  return {
    ...res,
    data: (res.data ?? []).map((c): PickerOption => {
      const branchCode = c.user?.branches?.[0]?.code;
      const parts = [c.employee_code, branchCode, c.is_active ? undefined : "inactive"].filter(Boolean);
      return {
        id: c.id,
        label: c.user?.name || c.employee_code || `#${c.id}`,
        description: parts.join(" · ") || undefined,
        meta: { collector: c },
      };
    }),
  };
}

export async function searchServices(q: string, opts?: { branchId?: number | string; customerId?: number | string }) {
  const res = await apiFetch<PickerService[]>(
    `/pickers/services${toQuery({ q: q || undefined, branch_id: opts?.branchId, customer_id: opts?.customerId, limit: 25 })}`,
  );
  return {
    ...res,
    data: (res.data ?? []).map(
      (s): PickerOption => ({
        id: s.id,
        label: s.service_number,
        description: [s.customer?.contact_name || s.customer?.company_name, s.package?.name]
          .filter(Boolean)
          .join(" · "),
        meta: { service: s },
      }),
    ),
  };
}

export async function searchEquipment(q: string, branchId?: number | string) {
  const res = await apiFetch<PickerEquipment[]>(
    `/pickers/equipment${toQuery({ q: q || undefined, branch_id: branchId, limit: 25 })}`,
  );
  return {
    ...res,
    data: (res.data ?? []).map(
      (e): PickerOption => ({
        id: e.id,
        label: e.serial_number || e.equipment_number || e.mac_address || `#${e.id}`,
        description: [e.product?.name_en, e.location?.name, e.custodian?.name].filter(Boolean).join(" · "),
        meta: { equipment: e },
      }),
    ),
  };
}

export async function searchProducts(q: string) {
  const res = await apiFetch<PickerProduct[]>(`/pickers/products${toQuery({ q: q || undefined, limit: 25 })}`);
  return {
    ...res,
    data: (res.data ?? []).map(
      (p): PickerOption => ({
        id: p.id,
        label: p.name_en,
        description: p.code,
        meta: { product: p },
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
        description: [c.customer_number, c.mobile || c.phone, c.branch?.code].filter(Boolean).join(" · "),
        meta: { customer: c },
      }),
    ),
  };
}
