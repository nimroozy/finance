import { apiFetch, toQuery } from "@/lib/api";
import type { AppNotification } from "@/lib/types";

export async function listNotifications(params: {
  page?: number;
  per_page?: number;
  unread_only?: boolean;
} = {}) {
  return apiFetch<AppNotification[]>(
    `/notifications${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 20,
      unread_only: params.unread_only,
    })}`,
  );
}

export async function markNotificationRead(id: number) {
  return apiFetch<AppNotification>(`/notifications/${id}/read`, {
    method: "POST",
  });
}

export async function markAllNotificationsRead() {
  return apiFetch<{ marked: number }>("/notifications/read-all", {
    method: "POST",
  });
}
