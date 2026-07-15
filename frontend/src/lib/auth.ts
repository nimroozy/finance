import { apiFetch } from "@/lib/api";
import type {
  AuthUser,
  Branch,
  BranchPayload,
  CreateUserPayload,
  LoginResponse,
  Role,
  SettingsPayload,
  SystemSettings,
  AuditLog,
} from "@/lib/types";
import { useAuthStore } from "@/store/auth-store";

export async function login(loginValue: string, password: string) {
  const response = await apiFetch<LoginResponse>("/auth/login", {
    method: "POST",
    body: JSON.stringify({ login: loginValue, password }),
  });

  useAuthStore
    .getState()
    .setAuth(response.data.token, response.data.user);

  return response.data;
}

export async function logout() {
  try {
    await apiFetch<null>("/auth/logout", { method: "POST" });
  } catch {
    // Clear local session even if API fails
  } finally {
    useAuthStore.getState().clearAuth();
  }
}

export async function fetchMe() {
  const response = await apiFetch<AuthUser>("/auth/me");
  useAuthStore.getState().setUser(response.data);
  return response.data;
}

export async function changePassword(payload: {
  current_password: string;
  password: string;
  password_confirmation: string;
}) {
  return apiFetch<{ message: string }>("/auth/change-password", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listUsers(page = 1) {
  return apiFetch<AuthUser[]>(`/users?page=${page}&per_page=15`);
}

export async function createUser(payload: CreateUserPayload) {
  return apiFetch<AuthUser>("/users", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function disableUser(id: number) {
  return apiFetch<AuthUser>(`/users/${id}/disable`, { method: "POST" });
}

export async function listBranches(page = 1) {
  return apiFetch<Branch[]>(`/branches?page=${page}&per_page=50`);
}

export async function createBranch(payload: BranchPayload) {
  return apiFetch<Branch>("/branches", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateBranch(id: number, payload: Partial<BranchPayload>) {
  return apiFetch<Branch>(`/branches/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function listRoles() {
  return apiFetch<Role[]>("/roles");
}

export async function listAuditLogs(page = 1) {
  return apiFetch<AuditLog[]>(`/audit-logs?page=${page}&per_page=25`);
}

export async function getSettings() {
  return apiFetch<SystemSettings>("/settings");
}

export async function updateSettings(payload: SettingsPayload) {
  return apiFetch<SystemSettings>("/settings", {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}
