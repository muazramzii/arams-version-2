/**
 * Typed API client.
 *
 * The backend returns one envelope for success ({ data, meta }) and one for
 * failure (RFC 7807-ish: { title, status, detail, errors }). Both are handled
 * here so no component has to guess at the shape.
 */

const BASE_URL = import.meta.env.VITE_API_URL ?? "/api/v1";
const TOKEN_KEY = "arams.token";

export class ApiError extends Error {
  // Declared explicitly rather than as constructor parameter properties:
  // the build runs with `erasableSyntaxOnly`, which disallows that shorthand.
  readonly status: number;
  readonly title: string;
  readonly detail: string;
  /** Field-level validation messages, keyed by input name. */
  readonly errors?: Record<string, string[]>;

  constructor(status: number, title: string, detail: string, errors?: Record<string, string[]>) {
    super(detail || title);
    this.name = "ApiError";
    this.status = status;
    this.title = title;
    this.detail = detail;
    this.errors = errors;
  }

  /** First message for a given field, for inline form feedback. */
  fieldError(field: string): string | undefined {
    return this.errors?.[field]?.[0];
  }
}

export const tokenStore = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
};

type RequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  query?: Record<string, string | number | boolean | undefined | null>;
  signal?: AbortSignal;
};

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = "GET", body, query, signal } = options;

  const url = new URL(`${BASE_URL}${path}`, window.location.origin);
  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      url.searchParams.set(key, String(value));
    }
  });

  const token = tokenStore.get();

  const response = await fetch(url.toString(), {
    method,
    signal,
    headers: {
      Accept: "application/json",
      ...(body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const isJson = response.headers.get("content-type")?.includes("application/json");
  const payload = isJson ? await response.json() : null;

  if (!response.ok) {
    // An expired or revoked token should not leave the UI in a half-signed-in
    // state — drop it and let the router send the user to the login screen.
    if (response.status === 401) {
      tokenStore.clear();
    }

    throw new ApiError(
      response.status,
      payload?.title ?? "Request failed",
      payload?.detail ?? response.statusText,
      payload?.errors,
    );
  }

  return payload as T;
}

export const api = {
  get: <T>(path: string, query?: RequestOptions["query"]) => request<T>(path, { query }),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: "POST", body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: "PUT", body }),
  delete: <T>(path: string, body?: unknown) => request<T>(path, { method: "DELETE", body }),
  /** Reports come back as a file, not JSON. */
  download: async (path: string) => {
    const token = tokenStore.get();
    const response = await fetch(`${BASE_URL}${path}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    });

    if (!response.ok) {
      throw new ApiError(response.status, "Download failed", "The report could not be downloaded.");
    }

    return response.blob();
  },
};
