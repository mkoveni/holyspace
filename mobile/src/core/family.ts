import createQr from "qrcode-generator";
import type { FamilyLabel, FamilySession } from "./models.ts";
export const readinessLabels: Record<string, string> = {
  IDENTITY: "Child identity and age",
  ENROLMENT: "Programme enrolment",
  CLASS: "Class placement",
  POLICY: "Current consent policy",
  GUARDIAN: "Verified guardian",
  CONSENT: "Guardian consent",
  COLLECTOR: "Authorized collector",
};
export function familyTime(value: string, timeZone: string): string {
  return new Intl.DateTimeFormat("en-ZA", {
    timeZone,
    weekday: "short",
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}
export function familyArrivalOpen(
  session: FamilySession,
  now: number,
): boolean {
  return (
    session.status === "OPEN" &&
    now >= Date.parse(session.startsAt) - 1800000 &&
    now < Date.parse(session.endsAt) &&
    session.issues.length === 0
  );
}
export function familyArrivalReason(
  session: FamilySession,
  now: number,
): string {
  if (
    session.status === "CLOSED" ||
    session.status === "CANCELLED" ||
    now >= Date.parse(session.endsAt)
  )
    return "Arrivals are closed. Staff can still complete authorized collections.";
  if (session.status !== "OPEN")
    return "The ministry team must open this session before arrivals.";
  if (now < Date.parse(session.startsAt) - 1800000)
    return `Arrivals open ${familyTime(new Date(Date.parse(session.startsAt) - 1800000).toISOString(), session.timeZone)} (${session.timeZone}).`;
  if (session.issues.length)
    return "Ask the ministry team to resolve the preparation items below.";
  return "Show this reference to the ministry team. Staff check consent, placement and available capacity before recording arrival.";
}
export function familyQr(value: string): string {
  const qr = createQr(0, "M");
  qr.addData(value);
  qr.make();
  return qr.createDataURL(6, 4);
}
export function arrivalReference(child: string, session: string): string {
  return `holyspace:child-arrival:v1:${session}:${child}`;
}
export function collectionReference(label: FamilyLabel): string {
  return `holyspace:child-collection:v1:${label.attendanceId}:${label.pickupCode}`;
}
