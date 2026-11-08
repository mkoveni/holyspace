import { afterEach, test } from "node:test";
import assert from "node:assert/strict";
import {
  ApiError,
  SessionChanged,
  apiOrigin,
  coverImageSource,
  createApi,
  safeHandoff,
} from "../src/core/http.ts";
const original = globalThis.fetch;
afterEach(() => {
  globalThis.fetch = original;
});
test("API origins require HTTPS outside explicit development and reject credentials or paths", () => {
  assert.equal(
    apiOrigin("https://api.example.org", false),
    "https://api.example.org",
  );
  assert.equal(apiOrigin("http://10.0.2.2:8082", true), "http://10.0.2.2:8082");
  for (const url of [
    "http://api.example.org",
    "https://user:secret@api.example.org",
    "https://api.example.org/path",
    "https://api.example.org/?token=x",
  ])
    assert.throws(() => apiOrigin(url, false));
});
test("browser handoffs are restricted to the configured API and opaque one-use route", () => {
  const token = "a".repeat(43);
  assert.equal(
    safeHandoff(
      `https://api.example.org/api/mobile-checkouts/${token}`,
      "https://api.example.org",
    ),
    `https://api.example.org/api/mobile-checkouts/${token}`,
  );
  for (const url of [
    `https://evil.example.org/api/mobile-checkouts/${token}`,
    `https://api.example.org/api/mobile-checkouts/${token}?token=secret`,
    "javascript:alert(1)",
    "https://api.example.org/api/me",
  ])
    assert.throws(() => safeHandoff(url, "https://api.example.org"));
});
test("requests carry bearer authentication only in headers and never retry mutations automatically", async () => {
  let calls = 0;
  globalThis.fetch = async (url, init) => {
    calls++;
    assert.equal(url, "https://api.example.org/api/my-giving/payments");
    assert.equal(
      (init?.headers as Record<string, string>).Authorization,
      "Bearer opaque-session",
    );
    throw new Error("offline");
  };
  const api = createApi(
    "https://api.example.org",
    () => "opaque-session",
    async () => {},
  );
  await assert.rejects(
    () => api("/api/my-giving/payments", "POST", { requestId: "same-id" }),
    /connection/,
  );
  assert.equal(calls, 1);
});
test("expired authenticated sessions clear credentials and return an actionable error", async () => {
  let cleared = false;
  globalThis.fetch = async () => new Response("{}", { status: 401 });
  const api = createApi(
    "https://api.example.org",
    () => "old",
    async () => {
      cleared = true;
    },
  );
  await assert.rejects(() => api("/api/me"), ApiError);
  assert.equal(cleared, true);
});
test("late responses and late JSON bodies cannot cross an account change", async () => {
  let token = "old";
  globalThis.fetch = async () => {
    token = "new";
    return Response.json({ private: "old-account" });
  };
  const api = createApi(
    "https://api.example.org",
    () => token,
    async () => {},
  );
  await assert.rejects(() => api("/api/member/profile"), SessionChanged);
  token = "old";
  globalThis.fetch = async () =>
    ({
      ok: true,
      status: 200,
      json: async () => {
        token = "new";
        return { private: "old-account" };
      },
    }) as Response;
  await assert.rejects(() => api("/api/member/profile"), SessionChanged);
});
test("cover images resolve only to the API's own public image route", () => {
  const origin = "https://api.example.org";
  const id = "0b6f8f1e-6c1a-4d2e-9f3a-2b7c8d9e0f12";
  assert.equal(coverImageSource(null, origin), null);
  assert.equal(coverImageSource("", origin), null);
  assert.equal(
    coverImageSource(`/api/public/cover-images/${id}`, origin),
    `${origin}/api/public/cover-images/${id}`,
  );
  assert.equal(
    coverImageSource(`${origin}/api/public/cover-images/${id}`, origin),
    `${origin}/api/public/cover-images/${id}`,
  );
  for (const value of [
    `https://evil.example/api/public/cover-images/${id}`,
    `//evil.example/api/public/cover-images/${id}`,
    `/api/public/cover-images/${id}?token=secret`,
    `/api/public/cover-images/${id}#x`,
    `https://user:pw@api.example.org/api/public/cover-images/${id}`,
    `/api/public/cover-images/${id}/../../member/events`,
    "/api/member/events",
    "/api/public/cover-images/not-an-id",
    "javascript:alert(1)",
    "data:image/png;base64,AAAA",
  ])
    assert.equal(coverImageSource(value, origin), null, value);
});
