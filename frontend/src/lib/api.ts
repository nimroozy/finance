import type {
  ApiErrorResponse,
  ApiSuccessResponse,
} from "@/lib/types";

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, "") || "/api/v1";

export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]>;

  constructor(
    message: string,
    status: number,
    errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

type TokenGetter = () => string | null;
type UnauthorizedHandler = () => void;

let getToken: TokenGetter = () => {
  if (typeof window === "undefined") return null;
  try {
    const raw = localStorage.getItem("auth-storage");
    if (!raw) return null;
    const parsed = JSON.parse(raw) as { state?: { token?: string | null } };
    return parsed.state?.token ?? null;
  } catch {
    return null;
  }
};

let onUnauthorized: UnauthorizedHandler = () => {
  if (typeof window === "undefined") return;
  try {
    localStorage.removeItem("auth-storage");
  } catch {
    // ignore
  }
  const locale = window.location.pathname.split("/")[1] || "en";
  const safeLocale = locale === "fa" ? "fa" : "en";
  if (!window.location.pathname.includes("/login")) {
    window.location.href = `/${safeLocale}/login`;
  }
};

export function configureApi(options: {
  getToken?: TokenGetter;
  onUnauthorized?: UnauthorizedHandler;
}) {
  if (options.getToken) getToken = options.getToken;
  if (options.onUnauthorized) onUnauthorized = options.onUnauthorized;
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<ApiSuccessResponse<T>> {
  const headers = new Headers(options.headers);
  if (!headers.has("Accept")) {
    headers.set("Accept", "application/json");
  }
  if (options.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const token = getToken();
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
  });

  let payload: ApiSuccessResponse<T> | ApiErrorResponse | null = null;
  try {
    payload = (await response.json()) as
      | ApiSuccessResponse<T>
      | ApiErrorResponse;
  } catch {
    payload = null;
  }

  if (response.status === 401) {
    onUnauthorized();
    const message =
      payload && !payload.success && payload.message
        ? payload.message
        : "Unauthenticated.";
    throw new ApiError(
      message,
      401,
      payload && !payload.success && payload.errors ? payload.errors : {},
    );
  }

  if (!response.ok || !payload || payload.success === false) {
    const message =
      payload && !payload.success && payload.message
        ? payload.message
        : `Request failed (${response.status})`;
    const errors =
      payload && !payload.success && payload.errors ? payload.errors : {};
    throw new ApiError(message, response.status, errors);
  }

  return payload;
}

export function getApiBase() {
  return API_BASE;
}

/** Build a query string from defined values (skips empty/null/undefined). */
export function toQuery(
  params: Record<string, string | number | boolean | null | undefined>,
): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/** Download a non-JSON response (e.g. CSV export) with bearer auth. */
export async function apiDownload(
  path: string,
  filenameFallback = "download.csv",
): Promise<void> {
  const headers = new Headers({
    Accept: "text/csv,application/octet-stream,*/*",
  });
  const token = getToken();
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE}${path}`, { headers });

  if (response.status === 401) {
    onUnauthorized();
    throw new ApiError("Unauthenticated.", 401);
  }

  if (!response.ok) {
    let message = `Download failed (${response.status})`;
    try {
      const payload = (await response.json()) as ApiErrorResponse;
      if (payload && !payload.success && payload.message) {
        message = payload.message;
      }
    } catch {
      // ignore non-JSON error body
    }
    throw new ApiError(message, response.status);
  }

  const blob = await response.blob();
  const disposition = response.headers.get("Content-Disposition") ?? "";
  const match = /filename\*?=(?:UTF-8'')?["']?([^"';]+)/i.exec(disposition);
  const filename = match?.[1]?.trim() || filenameFallback;

  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}
