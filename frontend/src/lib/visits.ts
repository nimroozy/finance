import { apiFetch, toQuery } from "@/lib/api";
import type {
  CollectionVisit,
  CreateVisitPayload,
  VisitListParams,
  VisitOutcome,
} from "@/lib/types";

export async function listVisits(params: VisitListParams = {}) {
  return apiFetch<CollectionVisit[]>(
    `/visits${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      customer_id: params.customer_id,
      collector_id: params.collector_id,
      outcome: params.outcome,
      branch_id: params.branch_id,
    })}`,
  );
}

export async function getVisit(id: number) {
  return apiFetch<CollectionVisit>(`/visits/${id}`);
}

export async function createVisit(payload: CreateVisitPayload) {
  return apiFetch<CollectionVisit>("/visits", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function addVisitCorrectionNote(id: number, correctionNote: string) {
  return apiFetch<CollectionVisit>(`/visits/${id}/correction-note`, {
    method: "POST",
    body: JSON.stringify({ correction_note: correctionNote }),
  });
}

export async function listVisitOutcomes() {
  return apiFetch<VisitOutcome[]>("/visits/outcomes");
}
