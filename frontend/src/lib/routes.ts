import { apiFetch, toQuery } from "@/lib/api";
import type {
  CollectionRoute,
  CreateRoutePayload,
  RouteListParams,
} from "@/lib/types";

export async function listRoutes(params: RouteListParams = {}) {
  return apiFetch<CollectionRoute[]>(
    `/routes${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      status: params.status,
      collector_id: params.collector_id,
      branch_id: params.branch_id,
    })}`,
  );
}

export async function getRoute(id: number) {
  return apiFetch<CollectionRoute>(`/routes/${id}`);
}

export async function createRoute(payload: CreateRoutePayload) {
  return apiFetch<CollectionRoute>("/routes", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateRoute(
  id: number,
  payload: Partial<CreateRoutePayload> & { collector_id?: number },
) {
  return apiFetch<CollectionRoute>(`/routes/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function publishRoute(id: number) {
  return apiFetch<CollectionRoute>(`/routes/${id}/publish`, { method: "POST" });
}

export async function startRoute(id: number) {
  return apiFetch<CollectionRoute>(`/routes/${id}/start`, { method: "POST" });
}

export async function completeRoute(id: number) {
  return apiFetch<CollectionRoute>(`/routes/${id}/complete`, { method: "POST" });
}

export async function cancelRoute(id: number, reason?: string) {
  return apiFetch<CollectionRoute>(`/routes/${id}/cancel`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function reorderRouteStops(
  id: number,
  stops: { id: number; sequence: number }[],
) {
  return apiFetch<CollectionRoute>(`/routes/${id}/stops`, {
    method: "PUT",
    body: JSON.stringify({ stops }),
  });
}
