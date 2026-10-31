import { afterEach, test } from "node:test";
import assert from "node:assert/strict";
import { SessionStore } from "../src/core/session-store.ts";
const original = globalThis.fetch;
afterEach(() => {
  globalThis.fetch = original;
});
function fixture() {
  const saved = new Map<string, string>();
  const storage = {
    read: async (key: string) => saved.get(key) || null,
    write: async (key: string, value: string) => {
      saved.set(key, value);
    },
    remove: async (key: string) => {
      saved.delete(key);
    },
  };
  return {
    saved,
    storage,
    store: new SessionStore(
      "https://api.example.org",
      storage,
      "Holyspace · Test device",
    ),
  };
}
const account = {
  id: "member",
  displayName: "Solly",
  email: "member@example.org",
  username: "member",
  status: "active",
};
test("native storage receives only the opaque session, never the password or account profile", async () => {
  const { saved, store } = fixture();
  globalThis.fetch = async (url, init) => {
    if (String(url).endsWith("/api/login_check")) {
      assert.equal(
        JSON.parse(String(init?.body)).deviceName,
        "Holyspace · Test device",
      );
      return Response.json({ token: "opaque" });
    }
    return Response.json(account);
  };
  await store.signIn("member", "never-store-this-password");
  assert.equal(store.getSnapshot().account?.id, "member");
  assert.deepEqual([...saved.values()], ["opaque"]);
});
test("a revoked saved session clears access during restoration", async () => {
  const { saved, store } = fixture();
  saved.set("holyspace.member.session", "revoked");
  globalThis.fetch = async () => new Response("{}", { status: 401 });
  await store.retry();
  assert.equal(store.getSnapshot().account, null);
  assert.equal(saved.has("holyspace.member.session"), false);
  assert.equal(store.getSnapshot().ready, true);
});
test("offline restoration keeps the credential but exposes no account data", async () => {
  const { saved, store } = fixture();
  saved.set("holyspace.member.session", "still-valid");
  globalThis.fetch = async () => {
    throw new Error("offline");
  };
  await store.retry();
  assert.equal(store.getSnapshot().account, null);
  assert.match(store.getSnapshot().error, /connection/);
  assert.equal(saved.get("holyspace.member.session"), "still-valid");
});
test("failed credential storage revokes the newly issued session and leaves no authenticated account", async () => {
  const { storage } = fixture();
  let revoked = 0;
  globalThis.fetch = async (url) => {
    if (String(url).endsWith("/api/login_check"))
      return Response.json({ token: "new-token" });
    if (String(url).endsWith("/api/logout")) {
      revoked++;
      return new Response(null, { status: 204 });
    }
    return Response.json(account);
  };
  const store = new SessionStore(
    "https://api.example.org",
    {
      ...storage,
      write: async () => {
        throw new Error("Device storage unavailable");
      },
    },
    "Test",
  );
  await assert.rejects(() => store.signIn("member", "password"));
  assert.equal(revoked, 1);
  assert.equal(store.getSnapshot().account, null);
});
test("logout clears local session and pending intent even when the service is offline", async () => {
  const { store, saved } = fixture();
  globalThis.fetch = async (url) =>
    String(url).endsWith("/api/login_check")
      ? Response.json({ token: "opaque" })
      : Response.json(account);
  await store.signIn("member", "password");
  await store.savePending({
    requestId: "uuid",
    fundId: "general",
    amountMinor: 10000,
    provider: "yoco",
    recurring: false,
  });
  globalThis.fetch = async () => {
    throw new Error("offline");
  };
  await assert.rejects(() => store.signOut());
  assert.equal(store.getSnapshot().account, null);
  assert.equal(saved.size, 0);
});

test("session invalidation clears encrypted pending intent as well as credentials", async () => {
  const { store, saved } = fixture();
  globalThis.fetch = async (url) =>
    String(url).endsWith("/api/login_check")
      ? Response.json({ token: "opaque" })
      : Response.json(account);
  await store.signIn("member", "password");
  await store.savePending({
    requestId: "uuid",
    fundId: "general",
    amountMinor: 5000,
    provider: "yoco",
    recurring: false,
  });
  await store.clear("Session expired");
  assert.equal(saved.size, 0);
  assert.equal(store.getSnapshot().account, null);
});

test("push cleanup is attempted before logout and never prevents local clearing", async () => {
  const { store } = fixture();
  const order: string[] = [];
  globalThis.fetch = async (url) => {
    if (String(url).endsWith("/api/login_check"))
      return Response.json({ token: "opaque" });
    if (String(url).endsWith("/api/logout")) {
      order.push("logout");
      return new Response(null, { status: 204 });
    }
    return Response.json(account);
  };
  await store.signIn("member", "password");
  const captured = store.getSnapshot().account;
  assert.equal(store.isCurrentAccount(captured), true);
  store.registerSignOutCleanup(async () => {
    order.push("push-disabled");
    throw new Error("offline");
  });
  await store.signOut();
  assert.deepEqual(order, ["push-disabled", "logout"]);
  assert.equal(store.isCurrentAccount(captured), false);
  assert.equal(store.getSnapshot().account, null);
});
