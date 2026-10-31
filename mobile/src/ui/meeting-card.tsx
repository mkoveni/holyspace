import { Pressable, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { router } from "expo-router";
import type { MemberMeeting } from "@/core/models";
import { attendanceLabel, meetingTime } from "@/core/communities";
import { colors, ui } from "./theme";
export function MeetingCard({ meeting }: { meeting: MemberMeeting }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`View meeting for ${meeting.communityName}, ${meetingTime(meeting)}`}
      onPress={() =>
        router.push({ pathname: "/meeting/[id]", params: { id: meeting.id } })
      }
      style={ui.card}
    >
      <View style={ui.row}>
        <Text style={[ui.eyebrow, { flex: 1 }]}>{meetingTime(meeting)}</Text>
        <Ionicons name="calendar-outline" color={colors.green} size={24} />
      </View>
      <Text style={ui.h2}>{meeting.communityName}</Text>
      <Text style={ui.body}>
        {meeting.venue} · {meeting.timeZone}
      </Text>
      <View
        style={[ui.row, { justifyContent: "flex-start", flexWrap: "wrap" }]}
      >
        {meeting.status !== "OPEN" && (
          <Text style={ui.badge}>
            {meeting.status === "CANCELLED"
              ? "Meeting cancelled"
              : "Meeting closed"}
          </Text>
        )}
        <Text style={ui.badge}>{attendanceLabel(meeting.attendance)}</Text>
      </View>
    </Pressable>
  );
}
