import createQr from "qrcode-generator";
import type { MemberEvent } from "./models.ts";
export function eventTime(event: MemberEvent): string {
  return new Intl.DateTimeFormat("en-ZA", {
    timeZone: event.timeZone,
    weekday: "short",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(event.startsAt));
}
export function canRegister(event: MemberEvent, now = Date.now()): boolean {
  return (
    event.registrationOpen &&
    event.status === "SCHEDULED" &&
    new Date(event.startsAt).getTime() > now &&
    (!event.booking || event.booking.status === "CANCELLED") &&
    (event.remainingPlaces === null ||
      event.remainingPlaces > 0 ||
      event.waitlistEnabled)
  );
}
export function admissionOpen(event: MemberEvent, now = Date.now()): boolean {
  return (
    event.status === "SCHEDULED" &&
    event.booking?.status === "CONFIRMED" &&
    event.booking.attendance !== "PRESENT" &&
    now >= new Date(event.startsAt).getTime() - 7200000 &&
    now < new Date(event.endsAt).getTime()
  );
}
export function attendanceQr(credential: string): string {
  if (!/^holyspace:event:v1:[A-Za-z0-9_-]{20,200}$/.test(credential))
    throw new Error("Invalid event attendance code.");
  const code = createQr(0, "M");
  code.addData(credential, "Byte");
  code.make();
  return code.createDataURL(6, 24);
}

export function attendanceAvailability(
  event: MemberEvent,
  now: number,
): string {
  if (event.status !== "SCHEDULED") return "This gathering has been cancelled.";
  if (event.booking?.attendance === "PRESENT")
    return "You have already been checked in.";
  if (event.booking?.status !== "CONFIRMED")
    return "A confirmed booking is required to show an attendance code.";
  const opensAt = new Date(event.startsAt).getTime() - 7200000;
  if (now < opensAt) {
    const opening = new Intl.DateTimeFormat("en-ZA", {
      timeZone: event.timeZone,
      weekday: "short",
      day: "numeric",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(opensAt));
    return `Your attendance code opens ${opening} (${event.timeZone}), two hours before the event.`;
  }
  if (now >= new Date(event.endsAt).getTime())
    return "This event has ended. Attendance codes are now closed.";
  return "Your attendance code is available now. Show it to the welcome team when you arrive.";
}
