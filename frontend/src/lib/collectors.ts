import { apiFetch, toQuery } from "@/lib/api";
import type {
  Collector,
  CollectorDashboard,
  CreateCollectorPayload,
  UpdateCollectorPayload,
} from "@/lib/types";

export async function listCollectors(params: {
  page?: number;
  per_page?: number;
  is_active?: boolean | string;
} = {}) {
  return apiFetch<Collector[]>(
    `/collectors${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 50,
      is_active: params.is_active,
    })}`,
  );
}

export async function createCollector(payload: CreateCollectorPayload) {
  return apiFetch<Collector>("/collectors", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateCollector(
  id: number,
  payload: UpdateCollectorPayload,
) {
  return apiFetch<Collector>(`/collectors/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function getCollectorDashboard() {
  return apiFetch<CollectorDashboard>("/collector/dashboard");
}
