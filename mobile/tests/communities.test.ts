import assert from "node:assert/strict";
import { test } from "node:test";
import {
  attendanceExplanation,
  attendanceLabel,
  communityRole,
  meetingTime,
} from "../src/core/communities.ts";
import type { MemberMeeting } from "../src/core/models.ts";
test("Own attendance keeps absence separate from unrecorded and unprepared states", () => {
  const statuses = [
    "PRESENT",
    "ABSENT",
    "EXCUSED",
    "UNRECORDED",
    "NOT_PREPARED",
    "NOT_LISTED",
  ];
  assert.equal(new Set(statuses.map(attendanceLabel)).size, statuses.length);
  assert.match(
    attendanceExplanation("NOT_PREPARED"),
    /does not mean you are absent/,
  );
  assert.match(attendanceExplanation("NOT_LISTED"), /Ask your group leader/);
  assert.equal(attendanceLabel("UNKNOWN"), "Attendance unavailable");
});
test("Meetings show the church schedule timezone and leadership stays distinct", () => {
  const meeting = {
    startsAt: "2026-10-28T08:00:00Z",
    timeZone: "Africa/Johannesburg",
  } as MemberMeeting;
  assert.match(meetingTime(meeting), /10:00/);
  assert.match(meetingTime({ ...meeting, timeZone: "UTC" }), /08:00/);
  assert.equal(communityRole("CO_LEADER"), "Co-leader");
  assert.equal(communityRole("MEMBER"), "Member");
});
