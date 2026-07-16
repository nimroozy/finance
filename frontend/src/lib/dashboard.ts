import { ApiError, apiFetch } from "@/lib/api";
import type { DashboardSummary } from "@/lib/types";

const EMPTY_SUMMARY: DashboardSummary = {};

export async function getDashboardSummary() {
  try {
    return await apiFetch<DashboardSummary>("/dashboard/summary");
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return { success: true as const, data: EMPTY_SUMMARY };
    }
    throw error;
  }
}
