import { apiFetch, toQuery } from "@/lib/api";
import type { CustomerNote } from "@/lib/types";

export async function listNotes(params: {
  page?: number;
  per_page?: number;
  customer_id?: number | string;
} = {}) {
  return apiFetch<CustomerNote[]>(
    `/notes${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      customer_id: params.customer_id,
    })}`,
  );
}

export async function createNote(payload: {
  customer_id: number;
  assignment_id?: number;
  body: string;
}) {
  return apiFetch<CustomerNote>("/notes", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateNote(id: number, body: string) {
  return apiFetch<CustomerNote>(`/notes/${id}`, {
    method: "PATCH",
    body: JSON.stringify({ body }),
  });
}
