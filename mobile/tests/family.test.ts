import assert from "node:assert/strict";
import { test } from "node:test";
import {
  familyArrivalOpen,
  familyArrivalReason,
  arrivalReference,
  collectionReference,
  familyQr,
} from "../src/core/family.ts";
import type { FamilySession } from "../src/core/models.ts";
const session: FamilySession = {
  id: "session",
  programme: "Sunday children",
  className: "Juniors",
  room: "Room A",
  location: "Main campus",
  timeZone: "Africa/Johannesburg",
  startsAt: "2026-11-01T08:00:00Z",
  endsAt: "2026-11-01T09:00:00Z",
  status: "OPEN",
  issues: [],
  arrivalWindowOpen: false,
};
test("Family arrival references respect the half-open arrival window and preparation", () => {
  const opens = Date.parse(session.startsAt) - 1800000;
  assert.equal(familyArrivalOpen(session, opens - 1), false);
  assert.equal(familyArrivalOpen(session, opens), true);
  assert.equal(familyArrivalOpen(session, Date.parse(session.endsAt)), false);
  assert.equal(
    familyArrivalOpen({ ...session, status: "PLANNED" }, opens),
    false,
  );
  assert.equal(
    familyArrivalOpen({ ...session, issues: ["Consent missing"] }, opens),
    false,
  );
  assert.match(
    familyArrivalReason(session, opens - 1),
    /09:30.*Africa\/Johannesburg/,
  );
});
test("Child arrival and collection QRs have distinct purposes and render locally", () => {
  const arrival = arrivalReference("child", "session"),
    collection = collectionReference({
      attendanceId: "attendance",
      pickupCode: "secret",
      labelVersion: 1,
      expiresAt: "2026-11-01T09:00:00Z",
      attendanceVersion: 1,
    });
  assert.equal(arrival, "divinecommunityspace:child-arrival:v1:session:child");
  assert.equal(
    collection,
    "divinecommunityspace:child-collection:v1:attendance:secret",
  );
  assert.ok(familyQr(collection).startsWith("data:image/gif;base64,"));
});
