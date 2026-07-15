import { apiDownload, apiFetch, getApiBase } from "@/lib/api";
import type { Receipt, ReceiptVerification } from "@/lib/types";

export async function getReceipt(uuid: string) {
  return apiFetch<Receipt>(`/receipts/${uuid}`);
}

export async function downloadReceiptPdf(uuid: string, filename?: string) {
  await apiDownload(`/receipts/${uuid}/pdf`, filename ?? `receipt-${uuid}.pdf`);
}

export async function logReceiptPrint(
  uuid: string,
  payload: { channel?: string; device_info?: string } = {},
) {
  return apiFetch<unknown>(`/receipts/${uuid}/print-log`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

/** Public verify endpoint — no auth required. */
export async function verifyReceipt(token: string) {
  const response = await fetch(
    `${getApiBase()}/verify-receipt/${encodeURIComponent(token)}`,
    { headers: { Accept: "application/json" } },
  );
  const payload = (await response.json()) as {
    success: boolean;
    data?: ReceiptVerification;
    message?: string;
  };
  if (!response.ok || !payload.success || !payload.data) {
    throw new Error(payload.message || `Verify failed (${response.status})`);
  }
  return payload.data;
}

export function receiptVerifyPath(token: string, locale = "en") {
  return `/${locale}/verify-receipt/${encodeURIComponent(token)}`;
}

export function receiptVerifyAbsoluteUrl(token: string, locale = "en") {
  if (typeof window === "undefined") {
    return receiptVerifyPath(token, locale);
  }
  return `${window.location.origin}${receiptVerifyPath(token, locale)}`;
}
