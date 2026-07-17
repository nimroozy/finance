import { apiFetch, apiUpload, toQuery } from "@/lib/api";

export const TASK_STATUSES = [
  "pending",
  "offered",
  "accepted",
  "rejected",
  "scheduled",
  "travelling",
  "arrived",
  "in_progress",
  "blocked",
  "waiting",
  "completed",
  "verification_pending",
  "approved",
  "cancelled",
  "failed",
] as const;

export const TASK_PRIORITIES = [
  "critical",
  "high",
  "medium",
  "normal",
  "low",
] as const;

export type TaskStatus = (typeof TASK_STATUSES)[number];
export type TaskPriority = (typeof TASK_PRIORITIES)[number];

export type Task = {
  id: number;
  task_number: string;
  branch_id: number;
  department_id?: number | null;
  team_id?: number | null;
  assignee_id?: number | null;
  created_by?: number | null;
  title: string;
  description?: string | null;
  type: string;
  priority: string;
  status: string;
  ticket_id?: number | null;
  installation_id?: number | null;
  customer_id?: number | null;
  due_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  location?: string | null;
  checklist?: unknown;
  requires_approval?: boolean;
  created_at?: string;
  updated_at?: string;
  assignee?: { id: number; name: string } | null;
  depends_on_tasks?: Array<{ id: number; task_number?: string; status?: string }>;
};

export type TaskListParams = {
  page?: number;
  per_page?: number;
  status?: string;
  ticket_id?: number | string;
  type?: string;
};

export type CreateTaskPayload = {
  branch_id: number;
  title: string;
  description?: string;
  type?: string;
  priority?: string;
  ticket_id?: number;
  installation_id?: number;
  assignee_id?: number;
  department_id?: number;
  team_id?: number;
  customer_id?: number;
  due_at?: string;
  depends_on?: number[];
  checklist?: unknown;
  requires_approval?: boolean;
};

export async function listTasks(params: TaskListParams = {}) {
  return apiFetch<Task[]>(
    `/tasks${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      status: params.status,
      ticket_id: params.ticket_id,
      type: params.type,
    })}`,
  );
}

export async function listMyTasks(params: { page?: number; per_page?: number } = {}) {
  return apiFetch<Task[]>(
    `/tasks/my${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
    })}`,
  );
}

export async function getTask(id: number | string) {
  return apiFetch<Task>(`/tasks/${id}`);
}

export async function createTask(payload: CreateTaskPayload) {
  return apiFetch<Task>("/tasks", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function acceptTask(id: number | string, reason?: string) {
  return apiFetch<Task>(`/tasks/${id}/accept`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function rejectTask(id: number | string, reason: string) {
  return apiFetch<Task>(`/tasks/${id}/reject`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function startTravelTask(id: number | string, reason?: string) {
  return apiFetch<Task>(`/tasks/${id}/start-travel`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function arriveTask(id: number | string, reason?: string) {
  return apiFetch<Task>(`/tasks/${id}/arrive`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function startTask(id: number | string, reason?: string) {
  return apiFetch<Task>(`/tasks/${id}/start`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function completeTask(id: number | string, reason?: string) {
  return apiFetch<Task>(`/tasks/${id}/complete`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function blockTask(id: number | string, reason: string) {
  return apiFetch<Task>(`/tasks/${id}/block`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function verifyTask(id: number | string, reason?: string) {
  return apiFetch<Task>(`/tasks/${id}/verify`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function reassignTask(
  id: number | string,
  userId: number,
  reason: string,
) {
  return apiFetch<Task>(`/tasks/${id}/reassign`, {
    method: "POST",
    body: JSON.stringify({ user_id: userId, reason }),
  });
}

export async function cancelTask(id: number | string, reason: string) {
  return apiFetch<Task>(`/tasks/${id}/cancel`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function uploadTaskAttachment(
  taskId: number | string,
  file: File,
  kind?: string,
) {
  const form = new FormData();
  form.append("file", file);
  form.append("attachable_type", "task");
  form.append("attachable_id", String(taskId));
  if (kind) form.append("kind", kind);
  return apiUpload<{ attachment: unknown; download_url?: string }>("/attachments", form);
}
