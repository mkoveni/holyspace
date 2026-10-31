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
import { Button, Message, Screen } from "@/ui/components";
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
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <Button title="Back" subtle onPress={() => router.back()} />
      <Message text={error} error />
      {data && (
        <>
          <View style={{ gap: 12 }}>
            <Text style={ui.eyebrow}>A MOMENT TO CONNECT</Text>
            <Text style={ui.title}>{data.communityName}</Text>
            <Text style={ui.body}>
              {meetingTime(data)} · {data.timeZone}
            </Text>
          </View>
          <View style={ui.card}>
            <Text style={ui.h2}>Your gathering</Text>
            <Text style={ui.body}>{data.venue}</Text>
            <Text style={ui.body}>
              Ends{" "}
              {new Date(data.endsAt).toLocaleTimeString("en-ZA", {
                timeZone: data.timeZone,
                hour: "2-digit",
                minute: "2-digit",
              })}{" "}
              · {data.timeZone}
            </Text>
            {data.status !== "OPEN" && (
              <Message
                text={
                  data.status === "CANCELLED"
                    ? "This meeting has been cancelled."
                    : "This meeting is closed."
                }
              />
            )}
          </View>
          <View style={ui.card}>
            <Text style={ui.eyebrow}>YOUR ATTENDANCE</Text>
            <Text style={ui.h2}>{attendanceLabel(data.attendance)}</Text>
            <Text style={ui.body}>
              {attendanceExplanation(data.attendance)}
            </Text>
            <Text style={ui.body}>
              Your leader manages attendance. Contact them if your record needs
              updating.
            </Text>
          </View>
        </>
      )}
    </Screen>
  );
}
