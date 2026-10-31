import { useState } from "react";
import { Text, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import type { MyCommunity, MyMeetings } from "@/core/models";
import { communityRole } from "@/core/communities";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen } from "@/ui/components";
import { MeetingCard } from "@/ui/meeting-card";
import { ui } from "@/ui/theme";
export default function CommunityDetail() {
  const { id } = useLocalSearchParams<{ id: string }>(),
    { api } = useSession();
  const [leaderOffset, setLeaderOffset] = useState(0),
    [meetingOffset, setMeetingOffset] = useState(0);
  const details = useLoad(
    () =>
      api<MyCommunity>(
        `/api/member/communities/${encodeURIComponent(id)}?leaderLimit=25&leaderOffset=${leaderOffset}`,
      ),
    [id, leaderOffset],
  );
  const meetings = useLoad(
    () =>
      api<MyMeetings>(
        `/api/member/group-meetings?communityId=${encodeURIComponent(id)}&limit=25&offset=${meetingOffset}`,
      ),
    [id, meetingOffset],
  );
  const community = details.data?.community;
  return (
    <Screen
      refreshing={details.loading || meetings.loading}
      onRefresh={() => {
        void details.reload();
        void meetings.reload();
      }}
    >
      <Button title="Back to my groups" subtle onPress={() => router.back()} />
      <Message text={details.error} error />
      {community && (
        <>
          <View style={{ gap: 12 }}>
            <Text style={ui.eyebrow}>
              {community.kind === "MINISTRY" ? "MY MINISTRY" : "MY GROUP"}
            </Text>
            <Text style={ui.title}>{community.name}</Text>
            <Text style={ui.body}>{community.campus}</Text>
          </View>
          <View style={ui.card}>
            <Text style={ui.h2}>A shared purpose.</Text>
            <Text style={ui.body}>
              {community.description || "A place to belong and grow together."}
            </Text>
            {community.parentName && (
              <Text style={ui.body}>Part of {community.parentName}</Text>
            )}
            <Text style={ui.badge}>
              Your role · {communityRole(community.role)}
            </Text>
            <Text style={ui.body}>
              Member since{" "}
              {new Date(community.joinedAt).toLocaleDateString("en-ZA", {
                day: "numeric",
                month: "long",
                year: "numeric",
              })}
            </Text>
          </View>
          <View style={ui.card}>
            <Text style={ui.eyebrow}>HERE TO WALK WITH YOU</Text>
            <Text style={ui.h2}>Your leaders</Text>
            {!details.data!.leaders.length && (
              <Text style={ui.body}>
                No current leaders to show on this page. The church office can
                help you find the right person.
              </Text>
            )}
            {details.data!.leaders.map((leader, index) => (
              <View
                key={`${leader.name}:${leader.role}:${index}`}
                style={[ui.row, { flexWrap: "wrap" }]}
              >
                <Text style={[ui.h2, { fontSize: 17 }]}>{leader.name}</Text>
                <Text style={ui.badge}>{communityRole(leader.role)}</Text>
              </View>
            ))}
            {(leaderOffset > 0 || details.data!.hasMoreLeaders) && (
              <View style={ui.row}>
                <Button
                  title="Previous leaders"
                  subtle
                  disabled={leaderOffset === 0 || details.loading}
                  onPress={() =>
                    setLeaderOffset(Math.max(0, leaderOffset - 25))
                  }
                />
                <Button
                  title="More leaders"
                  subtle
                  disabled={!details.data!.hasMoreLeaders || details.loading}
                  onPress={() => setLeaderOffset(leaderOffset + 25)}
                />
              </View>
            )}
          </View>
          <Text style={ui.eyebrow}>UPCOMING MEETINGS</Text>
          <Text style={ui.body}>
            Your gatherings over the next six months. Attendance is recorded by
            your group leader.
          </Text>
          <Message text={meetings.error} error />
          {meetings.data?.items.map((meeting) => (
            <MeetingCard key={meeting.id} meeting={meeting} />
          ))}
          {meetings.data?.items.length === 0 && (
            <View style={ui.card}>
              <Text style={ui.h2}>A little space in the calendar.</Text>
              <Text style={ui.body}>
                No upcoming meetings to show. Check back soon or contact your
                leader.
              </Text>
            </View>
          )}
          {(meetingOffset > 0 || meetings.data?.hasMore) && (
            <View style={ui.row}>
              <Button
                title="Previous meetings"
                subtle
                disabled={meetingOffset === 0 || meetings.loading}
                onPress={() =>
                  setMeetingOffset(Math.max(0, meetingOffset - 25))
                }
              />
              <Button
                title="More meetings"
                subtle
                disabled={!meetings.data?.hasMore || meetings.loading}
                onPress={() => setMeetingOffset(meetingOffset + 25)}
              />
            </View>
          )}
        </>
      )}
    </Screen>
  );
}
