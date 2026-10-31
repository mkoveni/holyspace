import assert from "node:assert/strict";
import { test } from "node:test";
import { canRespond, zonedInstant } from "../src/core/volunteering.ts";
import type { VolunteerDuty } from "../src/core/models.ts";
const duty: VolunteerDuty = {
  id: "a",
  status: "PROPOSED",
  shiftStatus: "OPEN",
  team: "Welcome",
  role: "Welcomer",
  activity: null,
  location: null,
  startsAt: "2026-11-01T08:00:00Z",
  endsAt: "2026-11-01T09:00:00Z",
  readiness: { ready: false, issues: ["Screening required"] },
  invitation: {
    id: "i",
    version: 0,
    status: "PENDING",
    expiresAt: "2026-11-01T08:00:00Z",
  },
};
test("Invitation response is distinct from readiness and expires at its boundary", () => {
  assert.equal(canRespond(duty, Date.parse("2026-11-01T07:59:59Z")), true);
  assert.equal(canRespond(duty, Date.parse("2026-11-01T08:00:00Z")), false);
  assert.equal(canRespond({ ...duty, shiftStatus: "CANCELLED" }, 0), false);
  assert.equal(canRespond({ ...duty, status: "CANCELLED" }, 0), false);
  assert.equal(
    canRespond(
      { ...duty, invitation: { ...duty.invitation!, status: "WITHDRAWN" } },
      0,
    ),
    false,
  );
});
test("Unavailable wall times resolve in the chosen zone and reject invalid or ambiguous dates", () => {
  assert.equal(
    zonedInstant("2026-11-01", "19:00", "Africa/Johannesburg"),
    "2026-11-01T17:00:00.000Z",
  );
  assert.equal(
    zonedInstant("2026-11-01", "00:00", "Africa/Johannesburg"),
    "2026-10-31T22:00:00.000Z",
  );
  assert.throws(
    () => zonedInstant("2026-03-08", "02:30", "America/New_York"),
    /daylight saving/,
  );
  assert.throws(
    () => zonedInstant("2026-11-01", "01:30", "America/New_York"),
    /daylight saving/,
  );
  assert.throws(
    () => zonedInstant("2026-02-30", "09:00", "Africa/Johannesburg"),
    /valid date/,
  );
});
