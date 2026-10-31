export class ApiError extends Error {
  readonly status: number;
  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}
export class SessionChanged extends Error {
  constructor() {
    super("Your session changed. Please refresh.");
  }
}
export function apiOrigin(value: string, development: boolean): string {
  const url = new URL(value);
  if (
    url.username ||
    url.password ||
    url.search ||
    url.hash ||
    url.pathname !== "/" ||
    (url.protocol !== "https:" && !(development && url.protocol === "http:"))
  )
    throw new Error("Configure a valid HTTPS API origin.");
  return url.origin;
}
export function safeHandoff(value: string, origin: string): string {
  const url = new URL(value);
  if (
    url.origin !== origin ||
    url.username ||
    url.password ||
    url.search ||
    url.hash ||
    !/^\/api\/mobile-checkouts\/[A-Za-z0-9_-]{20,200}$/.test(url.pathname)
  )
    throw new Error(
      "The secure checkout address could not be verified. Contact the church office.",
    );
  return url.href;
}
export function createApi(
  origin: string,
  token: () => string | null,
  expired: () => Promise<void>,
) {
  return async function request<T>(
    path: string,
    method = "GET",
    body?: unknown,
  ): Promise<T> {
    if (
      !path.startsWith("/api/") ||
      path.includes("://") ||
      path.includes("..")
    )
      throw new Error("Invalid API route.");
    const captured = token(),
      controller = new AbortController(),
      timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(origin + path, {
        method,
        signal: controller.signal,
        cache: "no-store",
        headers: {
          Accept: "application/json",
          ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
          ...(captured ? { Authorization: `Bearer ${captured}` } : {}),
        },
        ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
      });
      if (captured !== token()) throw new SessionChanged();
      if (response.status === 401 && captured) {
        await expired();
        throw new ApiError("Your session expired. Please sign in again.", 401);
      }
      if (!response.ok) {
        let message =
          response.status >= 500
            ? "The church service is temporarily unavailable. Please try again."
            : "This request could not be completed.";
        try {
          const data = await response.json();
          if (typeof data.message === "string") message = data.message;
        } catch {
          /* The service may return an empty error response. */
        }
        throw new ApiError(message, response.status);
      }
      const data =
        response.status === 204
          ? (undefined as T)
          : ((await response.json()) as T);
      if (captured !== token()) throw new SessionChanged();
      return data;
    } catch (error) {
      if (error instanceof ApiError || error instanceof SessionChanged)
        throw error;
      throw new Error(
        "Unable to reach the church. Check your connection and try again.",
      );
    } finally {
      clearTimeout(timeout);
    }
  };
}
