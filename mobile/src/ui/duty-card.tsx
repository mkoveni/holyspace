import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import type { VolunteerDuty } from "@/core/models";
import { canRespond, dutyTime } from "@/core/volunteering";
import { Badge, Button, IconTile, Message } from "./components";
import { colors, ui } from "./theme";
function Detail({
  icon,
  text,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  text: string;
}) {
  return (
    <View style={{ flexDirection: "row", gap: 8, alignItems: "center" }}>
      <Ionicons name={icon} size={15} color={colors.muted} />
      <Text style={[ui.body, { fontSize: 14, flex: 1 }]}>{text}</Text>
    </View>
  );
}
export function DutyCard({
  duty,
  onRespond,
  respondTitle = "Respond to invitation",
  now,
}: {
  duty: VolunteerDuty;
  onRespond?: () => void;
  respondTitle?: string;
  now: number;
}) {
  const cancelled =
    duty.status === "CANCELLED" || duty.shiftStatus === "CANCELLED";
  const respond = canRespond(duty, now) ? onRespond : undefined;
  return (
    <View
      style={[
        ui.card,
        { gap: 12 },
        respond && { borderColor: colors.goldBright },
      ]}
    >
      <View style={ui.row}>
        <View
          style={{
            flexDirection: "row",
            gap: 12,
            alignItems: "center",
            flex: 1,
          }}
        >
          <IconTile
            icon={cancelled ? "close-circle-outline" : "hand-left-outline"}
            tone={cancelled ? "danger" : respond ? "gold" : "success"}
          />
          <View style={{ flex: 1, gap: 2 }}>
            <Text
              style={[
                ui.eyebrow,
                cancelled && { color: colors.danger },
                !cancelled &&
                  duty.status === "CONFIRMED" && { color: colors.success },
              ]}
            >
              {cancelled
                ? "CANCELLED"
                : duty.status === "CONFIRMED"
                  ? "CONFIRMED DUTY"
                  : "PROPOSED DUTY"}
            </Text>
            <Text style={ui.h2}>{duty.team}</Text>
          </View>
        </View>
      </View>
      <Text style={[ui.body, { color: colors.text }]}>
        {duty.role}
        {duty.activity ? ` · ${duty.activity}` : ""}
      </Text>
      <View style={{ gap: 6 }}>
        <Detail
          icon="time-outline"
          text={`${dutyTime(duty.startsAt)} – ${dutyTime(duty.endsAt)} · ${Intl.DateTimeFormat().resolvedOptions().timeZone}`}
        />
        {Boolean(duty.location) && (
          <Detail icon="location-outline" text={duty.location ?? ""} />
        )}
      </View>
      {!cancelled && (
        <View style={{ gap: 6 }}>
          <Badge
            tone={duty.readiness.ready ? "success" : "warning"}
            text={
              duty.readiness.ready
                ? "Ready to serve"
                : "Readiness needs attention"
            }
          />
          {duty.readiness.issues.map((issue, i) => (
            <Text key={i} style={[ui.body, { fontSize: 14 }]}>
              • {issue}
            </Text>
          ))}
        </View>
      )}
      {duty.invitation && (
        <Text style={ui.caption}>
          Invitation: {duty.invitation.status.toLowerCase()}
          {duty.invitation.status === "PENDING"
            ? ` · respond by ${dutyTime(duty.invitation.expiresAt)}`
            : ""}
        </Text>
      )}
      {respond && (
        <Button title={respondTitle} variant="gold" onPress={respond} />
      )}
      {!cancelled && !duty.invitation && (
        <Message text="Your coordinator has not requested a response. Contact your team if this duty needs changing." />
      )}
    </View>
  );
}
