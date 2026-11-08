import assert from "node:assert/strict";
import { test } from "node:test";
import {
  admissionOpen,
  attendanceQr,
  attendanceAvailability,
  canRegister,
} from "../src/core/events.ts";
import type { MemberEvent } from "../src/core/models.ts";
const now = Date.parse("2026-10-28T08:00:00Z");
const event: MemberEvent = {
  id: "event",
  eventId: "definition",
  title: "Gathering",
  description: "",
  startsAt: "2026-10-28T09:00:00Z",
  endsAt: "2026-10-28T10:00:00Z",
  timeZone: "UTC",
  venueName: "Hall",
  venueAddress: "",
  status: "SCHEDULED",
  registrationOpen: true,
  waitlistEnabled: true,
  remainingPlaces: 0,
  booking: null,
};
test("Booking choices distinguish capacity, waitlists and closed dates", () => {
  assert.equal(canRegister(event, now), true);
  assert.equal(canRegister({ ...event, waitlistEnabled: false }, now), false);
  assert.equal(canRegister({ ...event, status: "CANCELLED" }, now), false);
  assert.equal(canRegister(event, Date.parse(event.startsAt)), false);
  const booked = {
    ...event,
    booking: {
      id: "booking",
      status: "CONFIRMED",
      version: 0,
      attendance: null,
    },
  };
  assert.equal(canRegister(booked, now), false);
  assert.equal(
    canRegister(
      { ...booked, booking: { ...booked.booking, status: "CANCELLED" } },
      now,
    ),
    true,
  );
});
test("Attendance requires a confirmed booking within the half-open admission window", () => {
  const booked = {
    ...event,
    booking: {
      id: "booking",
      status: "CONFIRMED",
      version: 0,
      attendance: null,
    },
  };
  assert.equal(admissionOpen(booked, now), true);
  assert.equal(
    admissionOpen(booked, Date.parse(event.startsAt) - 7200001),
    false,
  );
  assert.equal(admissionOpen(booked, Date.parse(event.endsAt)), false);
  assert.equal(
    admissionOpen(
      { ...booked, booking: { ...booked.booking, status: "WAITLISTED" } },
      now,
    ),
    false,
  );
  assert.equal(
    admissionOpen(
      { ...booked, booking: { ...booked.booking, attendance: "PRESENT" } },
      now,
    ),
    false,
  );
});
test("Event QR is generated locally and rejects child collection and URL credentials", () => {
  const image = attendanceQr(
    "divinecommunityspace:event:v1:abcdefghijklmnopqrstuvwxyz0123456789",
  );
  assert.ok(image.startsWith("data:image/gif;base64,"));
  assert.equal(
    Buffer.from(image.split(",")[1], "base64").subarray(0, 3).toString(),
    "GIF",
  );
  assert.throws(() => attendanceQr("child-collection-code"));
  assert.throws(() =>
    attendanceQr("https://example.org/attendance?token=secret"),
  );
});

test("Disabled attendance codes explain the exact opening time in the gathering timezone", () => {
  const booked = {
    ...event,
    timeZone: "Africa/Johannesburg",
    booking: {
      id: "booking",
      status: "CONFIRMED",
      version: 0,
      attendance: null,
    },
  };
  const message = attendanceAvailability(
    booked,
    Date.parse("2026-10-27T08:00:00Z"),
  );
  assert.match(message, /28 Oct 2026/);
  assert.match(message, /09:00/);
  assert.match(message, /Africa\/Johannesburg/);
  assert.match(attendanceAvailability(booked, now), /available now/);
  assert.match(
    attendanceAvailability(booked, Date.parse(event.endsAt)),
    /event has ended/,
  );
});
