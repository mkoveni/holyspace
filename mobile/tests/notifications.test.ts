import { test } from "node:test";
import assert from "node:assert/strict";
import {
  notificationDestination,
  notificationTopic,
  unreadLabel,
} from "../src/core/notifications.ts";
test("only fixed member screens can be opened from a notification", () => {
  assert.equal(notificationDestination("VOLUNTEERING"), "/volunteering");
  assert.equal(notificationDestination("EVENTS"), "/events");
  for (const input of [
    "https://evil.example",
    "/children",
    "holyspace:child-collection:v1:secret",
    "javascript:alert(1)",
    "",
  ])
    assert.equal(notificationDestination(input), null);
});
test("topic and count labels remain readable", () => {
  assert.equal(notificationTopic("VOLUNTEERING"), "Serving & volunteering");
  assert.equal(unreadLabel(0), "0 unread");
  assert.equal(unreadLabel(101), "99+ unread");
});
