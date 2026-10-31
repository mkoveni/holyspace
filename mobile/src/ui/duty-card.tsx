import { Text, View } from "react-native";
import type { VolunteerDuty } from "@/core/models";
import { canRespond, dutyTime } from "@/core/volunteering";
import { Button, Message } from "./components";
import { colors, ui } from "./theme";
export function DutyCard({
  duty,
  onRespond,
  now,
}: {
  duty: VolunteerDuty;
  onRespond?: () => void;
  now: number;
}) {
  const cancelled =
    duty.status === "CANCELLED" || duty.shiftStatus === "CANCELLED";
  return (
    <View style={[ui.card, { gap: 12 }]}>
      <Text style={ui.eyebrow}>
        {cancelled
          ? "CANCELLED"
          : duty.status === "CONFIRMED"
            ? "CONFIRMED DUTY"
            : "PROPOSED DUTY"}
      </Text>
      <Text style={ui.h2}>{duty.team}</Text>
      <Text style={ui.body}>
        {duty.role}
        {duty.activity ? ` · ${duty.activity}` : ""}
      </Text>
      <Text style={{ color: colors.text, fontWeight: "600" }}>
        {dutyTime(duty.startsAt)} – {dutyTime(duty.endsAt)}
      </Text>
      <Text style={ui.body}>
        {Intl.DateTimeFormat().resolvedOptions().timeZone}
      </Text>
      {duty.location && <Text style={ui.body}>{duty.location}</Text>}
      {!cancelled && (
        <>
          <Text
            style={[
              ui.label,
              { color: duty.readiness.ready ? colors.green : colors.gold },
            ]}
          >
            {duty.readiness.ready
              ? "Ready to serve"
              : "Readiness needs attention"}
          </Text>
          {duty.readiness.issues.map((issue, i) => (
            <Text key={i} style={ui.body}>
              • {issue}
            </Text>
          ))}
        </>
      )}
      {duty.invitation && (
        <Text style={ui.body}>
          Invitation: {duty.invitation.status.toLowerCase()}
          {duty.invitation.status === "PENDING"
            ? ` · respond by ${dutyTime(duty.invitation.expiresAt)}`
            : ""}
        </Text>
      )}
      {canRespond(duty, now) && onRespond && (
        <Button title="Respond to invitation" onPress={onRespond} />
      )}
      {!cancelled && !duty.invitation && (
        <Message text="Your coordinator has not requested a response. Contact your team if this duty needs changing." />
      )}
    </View>
  );
}
