import { Pressable, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { router } from "expo-router";
import type { MemberMeeting } from "@/core/models";
import { attendanceLabel, meetingTime } from "@/core/communities";
import { eventDay } from "@/core/events";
import { Badge } from "./components";
import { DateTile } from "./event-card";
import { colors, ui } from "./theme";
export function MeetingCard({ meeting }: { meeting: MemberMeeting }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`View meeting for ${meeting.communityName}, ${meetingTime(meeting)}`}
      onPress={() =>
        router.push({ pathname: "/meeting/[id]", params: { id: meeting.id } })
      }
      style={({ pressed }) => [
        ui.card,
        {
          flexDirection: "row",
          alignItems: "center",
          gap: 14,
          padding: 14,
          transform: [{ scale: pressed ? 0.99 : 1 }],
        },
      ]}
    >
      <DateTile startsAt={meeting.startsAt} timeZone={meeting.timeZone} />
      <View style={{ flex: 1, gap: 4 }}>
        <Text style={ui.h3} numberOfLines={2}>
          {meeting.communityName}
        </Text>
        <Text style={ui.caption} numberOfLines={1}>
          {eventDay(meeting).time} · {meeting.venue}
        </Text>
        <View
          style={{
            flexDirection: "row",
            flexWrap: "wrap",
            gap: 6,
            marginTop: 4,
          }}
        >
          {meeting.status !== "OPEN" && (
            <Badge
              tone={meeting.status === "CANCELLED" ? "danger" : "neutral"}
              text={
                meeting.status === "CANCELLED"
                  ? "Meeting cancelled"
                  : "Meeting closed"
              }
            />
          )}
          <Badge
            tone={meeting.attendance === "PRESENT" ? "success" : "neutral"}
            text={attendanceLabel(meeting.attendance)}
          />
        </View>
      </View>
      <Ionicons name="chevron-forward" size={18} color={colors.faint} />
    </Pressable>
  );
}
