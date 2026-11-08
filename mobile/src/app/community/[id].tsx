import { useState } from "react";
import { Text, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import type { MyCommunity, MyMeetings } from "@/core/models";
import { communityRole } from "@/core/communities";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import {
  Avatar,
  Badge,
  EmptyState,
  Message,
  PageHeader,
  Pager,
  Screen,
  SectionHeader,
} from "@/ui/components";
import { CoverBanner } from "@/ui/cover-image";
import { MeetingCard } from "@/ui/meeting-card";
import { colors, ui } from "@/ui/theme";
import { SkeletonBlock } from "@/ui/skeleton";
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
      back={{ label: "Back to my groups", onPress: () => router.back() }}
    >
      <Message text={details.error} error />
      {!details.data && !details.error && <SkeletonBlock />}
      {community && (
        <>
          <CoverBanner
            url={community.coverImageUrl}
            label={`Cover image for ${community.name}`}
          />
          <PageHeader
            eyebrow={community.kind === "MINISTRY" ? "My ministry" : "My group"}
            title={community.name}
            intro={community.campus || undefined}
            icon={
              community.kind === "MINISTRY" ? "leaf-outline" : "people-outline"
            }
          />
          <View style={ui.card}>
            <Text style={ui.h2}>A shared purpose.</Text>
            <Text style={ui.body}>
              {community.description || "A place to belong and grow together."}
            </Text>
            {Boolean(community.parentName) && (
              <Text style={ui.body}>Part of {community.parentName}</Text>
            )}
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
              <Badge
                tone="gold"
                text={`Your role · ${communityRole(community.role)}`}
              />
              <Badge
                text={`Member since ${new Date(
                  community.joinedAt,
                ).toLocaleDateString("en-ZA", {
                  day: "numeric",
                  month: "long",
                  year: "numeric",
                })}`}
              />
            </View>
          </View>
          <SectionHeader title="Your leaders" />
          {!details.data!.leaders.length ? (
            <EmptyState
              icon="person-outline"
              text="No current leaders to show on this page. The church office can help you find the right person."
            />
          ) : (
            <View style={ui.group}>
              {details.data!.leaders.map((leader, index, all) => (
                <View
                  key={`${leader.name}:${leader.role}:${index}`}
                  style={[
                    ui.row,
                    {
                      justifyContent: "flex-start",
                      padding: 14,
                      borderBottomWidth: index === all.length - 1 ? 0 : 1,
                      borderColor: colors.line,
                    },
                  ]}
                >
                  <Avatar name={leader.name} />
                  <Text style={[ui.h3, { flex: 1 }]}>{leader.name}</Text>
                  <Badge text={communityRole(leader.role)} tone="gold" />
                </View>
              ))}
            </View>
          )}
          <Pager
            hasPrevious={leaderOffset > 0}
            hasNext={details.data!.hasMoreLeaders}
            disabled={details.loading}
            previous={{
              title: "Previous leaders",
              onPress: () => setLeaderOffset(Math.max(0, leaderOffset - 25)),
            }}
            next={{
              title: "More leaders",
              onPress: () => setLeaderOffset(leaderOffset + 25),
            }}
          />
          <SectionHeader title="Upcoming meetings" />
          <Text style={[ui.body, { fontSize: 14, marginTop: -8 }]}>
            Your gatherings over the next six months. Attendance is recorded by
            your group leader.
          </Text>
          <Message text={meetings.error} error />
          {meetings.data?.items.map((meeting) => (
            <MeetingCard key={meeting.id} meeting={meeting} />
          ))}
          {meetings.data?.items.length === 0 && (
            <EmptyState
              icon="calendar-outline"
              title="A little space in the calendar."
              text="No upcoming meetings to show. Check back soon or contact your leader."
            />
          )}
          <Pager
            hasPrevious={meetingOffset > 0}
            hasNext={Boolean(meetings.data?.hasMore)}
            disabled={meetings.loading}
            previous={{
              title: "Previous meetings",
              onPress: () => setMeetingOffset(Math.max(0, meetingOffset - 25)),
            }}
            next={{
              title: "More meetings",
              onPress: () => setMeetingOffset(meetingOffset + 25),
            }}
          />
        </>
      )}
    </Screen>
  );
}
