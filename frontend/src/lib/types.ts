export type UserStatus = "active" | "disabled";

export interface BranchSummary {
  id: number;
  code: string;
  name_en: string;
  name_fa?: string;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  username: string | null;
  phone?: string | null;
  locale: string;
  status: UserStatus;
  force_password_change: boolean;
  last_login_at?: string | null;
  roles: string[];
  permissions: string[];
  branches: BranchSummary[];
}

export interface Branch {
  id: number;
  code: string;
  name_en: string;
  name_fa: string;
  province_en: string;
  province_fa: string;
  phone: string | null;
  address: string | null;
  receipt_prefix: string;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface Role {
  id: number;
  name: string;
  permissions: string[];
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AuditLog {
  id: number;
  user_id: number | null;
  branch_id: number | null;
  action: string;
  entity_type: string | null;
  entity_id: number | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  user?: { id: number; name: string; email: string } | null;
  branch?: { id: number; code: string; name_en: string } | null;
}

export interface SystemSettings {
  company_name: string | null;
  currency: string | null;
  timezone: string | null;
  setup_completed?: boolean | string | null;
}

export interface ApiSuccessResponse<T> {
  success: true;
  data: T;
  meta?: PaginationMeta;
  message?: string;
}

export interface ApiErrorResponse {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

export interface LoginResponse {
  token: string;
  token_type: string;
  user: AuthUser;
  force_password_change: boolean;
}

export interface CreateUserPayload {
  name: string;
  email: string;
  username?: string;
  password: string;
  locale?: "en" | "fa";
  roles?: string[];
  branch_ids?: number[];
  force_password_change?: boolean;
}

export interface BranchPayload {
  code: string;
  name_en: string;
  name_fa: string;
  province_en: string;
  province_fa: string;
  phone?: string;
  address?: string;
  receipt_prefix: string;
  is_active?: boolean;
}

export interface SettingsPayload {
  company_name?: string;
  currency?: string;
  timezone?: string;
}
