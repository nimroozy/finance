import { apiDownload, apiUpload } from "@/lib/api";
import type { VisitFile } from "@/lib/types";

export async function uploadVisitFile(visitId: number, file: File) {
  const formData = new FormData();
  formData.append("file", file);
  return apiUpload<VisitFile>(`/visits/${visitId}/files`, formData);
}

export async function downloadVisitFile(
  fileId: number,
  filename = "evidence.bin",
) {
  await apiDownload(`/files/${fileId}/download`, filename);
}
