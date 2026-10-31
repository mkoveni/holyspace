import type { VolunteerDuty } from "./models.ts";
export const weekDays = [
  "Monday",
  "Tuesday",
  "Wednesday",
  "Thursday",
  "Friday",
  "Saturday",
  "Sunday",
];
export function canRespond(duty: VolunteerDuty, now = Date.now()): boolean {
  return (
    duty.shiftStatus === "OPEN" &&
    duty.status !== "CANCELLED" &&
    duty.invitation?.status === "PENDING" &&
    new Date(duty.invitation.expiresAt).getTime() > now
  );
}
export function dutyTime(
  instant: string,
  zone = Intl.DateTimeFormat().resolvedOptions().timeZone,
): string {
  return new Intl.DateTimeFormat("en-ZA", {
    timeZone: zone,
    weekday: "short",
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(instant));
}
export function localDate(date = new Date()): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}
// Resolve the chosen wall time explicitly. DST gaps and duplicate times need a different selection.
export function zonedInstant(date: string, time: string, zone: string): string {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time))
    throw new Error("Choose a date and time.");
  const target = Date.parse(`${date}T${time}:00Z`);
  if (
    !Number.isFinite(target) ||
    new Date(target).toISOString().slice(0, 16) !== `${date}T${time}`
  )
    throw new Error("Choose a valid date and time.");
  const formatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: zone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hourCycle: "h23",
  });
  const parts = (at: number) => {
    const p = Object.fromEntries(
      formatter.formatToParts(new Date(at)).map((v) => [v.type, v.value]),
    );
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}:${p.second}`;
  };
  const offsets = new Set<number>();
  for (let hours = -36; hours <= 36; hours += 6) {
    const sample = target + hours * 3600000;
    offsets.add(Date.parse(`${parts(sample)}Z`) - sample);
  }
  const candidates = [...offsets]
    .map((offset) => target - offset)
    .filter((at) => parts(at) === `${date}T${time}:00`);
  if (candidates.length !== 1)
    throw new Error(
      "This time is skipped or repeated by daylight saving. Choose another time.",
    );
  return new Date(candidates[0]).toISOString();
}
