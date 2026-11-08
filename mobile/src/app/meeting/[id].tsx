import { Text, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import type { MemberMeeting } from "@/core/models";
import {
  attendanceLabel,
  attendanceExplanation,
  meetingTime,
} from "@/core/communities";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { IconTile, InfoRow, Message, Screen } from "@/ui/components";
import { DateTile } from "@/ui/event-card";
import { ui } from "@/ui/theme";
export default function MeetingDetail() {
  const { id } = useLocalSearchParams<{ id: string }>(),
    { api } = useSession();
  const { data, loading, error, reload } = useLoad(
    () =>
      api<MemberMeeting>(
        `/api/member/group-meetings/${encodeURIComponent(id)}`,
      ),
    [id],
  );
  return (
    <Screen
      refreshing={loading}
      onRefresh={() => void reload()}
      back={{ label: "Back", onPress: () => router.back() }}
    >
      <Message text={error} error />
      {data && (
        <>
          <View style={{ flexDirection: "row", gap: 16, alignItems: "center" }}>
            <DateTile startsAt={data.startsAt} timeZone={data.timeZone} />
            <View style={{ flex: 1, gap: 6 }}>
              <Text style={ui.eyebrow}>A MOMENT TO CONNECT</Text>
              <Text
                accessibilityRole="header"
                style={[ui.title, { fontSize: 28, lineHeight: 34 }]}
              >
                {data.communityName}
              </Text>
            </View>
          </View>
          {data.status !== "OPEN" && (
            <Message
              text={
                data.status === "CANCELLED"
                  ? "This meeting has been cancelled."
                  : "This meeting is closed."
              }
            />
          )}
          <View style={ui.group}>
            <InfoRow
              icon="time-outline"
              title={`${meetingTime(data)} – ${new Date(
                data.endsAt,
              ).toLocaleTimeString("en-ZA", {
                timeZone: data.timeZone,
                hour: "2-digit",
                minute: "2-digit",
              })}`}
              subtitle={data.timeZone}
            />
            <InfoRow last icon="location-outline" title={data.venue} />
          </View>
          <View style={ui.card}>
            <View
              style={{ flexDirection: "row", gap: 12, alignItems: "center" }}
            >
              <IconTile
                icon={
                  data.attendance === "PRESENT"
                    ? "checkmark-circle-outline"
                    : "clipboard-outline"
                }
                tone={data.attendance === "PRESENT" ? "success" : "gold"}
              />
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={ui.eyebrow}>YOUR ATTENDANCE</Text>
                <Text style={ui.h2}>{attendanceLabel(data.attendance)}</Text>
              </View>
            </View>
            <Text style={ui.body}>
              {attendanceExplanation(data.attendance)}
            </Text>
            <Text style={ui.caption}>
              Your leader manages attendance. Contact them if your record needs
              updating.
            </Text>
          </View>
        </>
      )}
    </Screen>
  );
}
