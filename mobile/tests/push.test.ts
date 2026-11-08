import { test } from "node:test";
import assert from "node:assert/strict";
import {
  pushNotificationId,
  canRenewPush,
  resolvePushDestination,
} from "../src/core/push.ts";
const id = "11111111-1111-4111-8111-111111111111";
test("only typed own-notification identifiers can be resolved; routes and QR credentials are ignored", () => {
  assert.equal(
    pushNotificationId({
      type: "divinecommunityspace.member-update.v1",
      notificationId: id,
    }),
    id,
  );
  for (const data of [
    null,
    { url: "https://evil.example" },
    {
      type: "divinecommunityspace.member-update.v1",
      notificationId: "../other",
    },
    { type: "child-collection", notificationId: id },
  ])
    assert.equal(pushNotificationId(data), null);
});
test("automatic renewal never revives a remote revocation or another account's opt-in", () => {
  const device = {
    id,
    platform: "android",
    status: "ACTIVE",
    currentSession: true,
    expiresAt: "",
    updatedAt: "",
  };
  assert.equal(canRenewPush("a", "a", [device], id), true);
  assert.equal(canRenewPush("a", "b", [device], id), false);
  assert.equal(
    canRenewPush("a", "a", [{ ...device, status: "REVOKED" }], id),
    false,
  );
  assert.equal(canRenewPush("a", "a", [], id), false);
});

test("push taps fetch the signed-in member's notice and ignore supplied URLs", async () => {
  const data = {
    type: "divinecommunityspace.member-update.v1",
    notificationId: id,
    destination: "https://evil.example",
  };
  const lookups: string[] = [];
  assert.equal(
    await resolvePushDestination(
      data,
      async (value) => {
        lookups.push(value);
        return { destination: "EVENTS" };
      },
      () => true,
    ),
    "/events",
  );
  assert.deepEqual(lookups, [id]);
  assert.equal(
    await resolvePushDestination(
      data,
      async () => ({ destination: "https://evil.example" }),
      () => true,
    ),
    null,
  );
  assert.equal(
    await resolvePushDestination(
      data,
      async () => {
        throw new Error("foreign notice");
      },
      () => true,
    ),
    null,
  );
});
test("a delayed notification lookup cannot navigate after sign-out or an account change", async () => {
  let current = true;
  let complete: (notice: { destination: string }) => void = () => {};
  const promise = resolvePushDestination(
    { type: "divinecommunityspace.member-update.v1", notificationId: id },
    () =>
      new Promise((resolve) => {
        complete = resolve;
      }),
    () => current,
  );
  current = false;
  complete({ destination: "VOLUNTEERING" });
  assert.equal(await promise, null);
  let requests = 0;
  assert.equal(
    await resolvePushDestination(
      { type: "divinecommunityspace.member-update.v1", notificationId: id },
      async () => {
        requests++;
        return { destination: "EVENTS" };
      },
      () => false,
    ),
    null,
  );
  assert.equal(requests, 0);
});
