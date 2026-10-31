import type { MemberMeeting } from "./models.ts";
export function communityRole(role: string): string {
  return (
    (
      { MEMBER: "Member", LEADER: "Leader", CO_LEADER: "Co-leader" } as Record<
        string,
        string
      >
    )[role] || "Member"
  );
}
export function meetingTime(meeting: MemberMeeting): string {
  return new Intl.DateTimeFormat("en-ZA", {
    timeZone: meeting.timeZone,
    weekday: "short",
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(meeting.startsAt));
}
export function attendanceLabel(status: string): string {
  return (
    (
      {
        PRESENT: "Present",
        ABSENT: "Absent",
        EXCUSED: "Excused",
        UNRECORDED: "Not recorded",
        NOT_PREPARED: "Attendance not prepared",
        NOT_LISTED: "Not on the prepared attendance list",
      } as Record<string, string>
    )[status] || "Attendance unavailable"
  );
}
export function attendanceExplanation(status: string): string {
  return (
    (
      {
        PRESENT: "Your group leader has recorded you as present.",
        ABSENT:
          "Your group leader has recorded you as absent. Contact them if this needs correcting.",
        EXCUSED: "Your group leader has recorded an excused absence.",
        UNRECORDED:
          "You are on the attendance list, but no attendance has been recorded yet.",
        NOT_PREPARED:
          "The attendance list has not been prepared yet. This does not mean you are absent.",
        NOT_LISTED:
          "You were not included when the attendance list was prepared. Ask your group leader if you should be included.",
      } as Record<string, string>
    )[status] || "Contact your group leader for attendance information."
  );
}
