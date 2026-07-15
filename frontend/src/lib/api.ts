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
