import { useDevicePush } from "@/core/device-push";
import { useEffect, useState } from "react";
import { Text, View } from "react-native";
import { router } from "expo-router";
import type {
  MemberInbox,
  MemberProfile,
  MemberEvents,
  MyMeetings,
  MyVolunteering,
} from "@/core/models";
import { admissionOpen, eventDay } from "@/core/events";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import {
  Appear,
  Button,
  IconButton,
  LinkButton,
  ListRow,
  Message,
  QuickAction,
  Screen,
  SectionHeader,
} from "@/ui/components";
import { MeetingCard } from "@/ui/meeting-card";
import { DutyCard } from "@/ui/duty-card";
import { DateTile, EventCard } from "@/ui/event-card";
import { colors, fonts, ui } from "@/ui/theme";
import { SkeletonList } from "@/ui/skeleton";

function greeting(hour: number): string {
  if (hour < 12) return "Good morning";
  if (hour < 18) return "Good afternoon";
  return "Good evening";
}

export default function Home() {
  const push = useDevicePush();
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, []);
  const { api, account } = useSession(),
    { data, loading, error, reload } = useLoad(() =>
      api<MemberProfile>("/api/member/profile"),
    );
  const upcoming = useLoad(() =>
    api<MemberEvents>("/api/member/events?mine=true&limit=5"),
  );
  const meetings = useLoad(() =>
    api<MyMeetings>("/api/member/group-meetings?limit=3"),
  );
  const volunteering = useLoad(() =>
    api<MyVolunteering>("/api/member/volunteering?limit=5"),
  );
  const inbox = useLoad(
    () => api<MemberInbox>("/api/member/notifications?limit=1"),
    [push.revision],
  );
  const nextDuty = volunteering.data?.items.find(
    (d) => d.status !== "CANCELLED" && d.shiftStatus === "OPEN",
  );
  const booked =
    upcoming.data?.items.filter(
      (event) => event.booking?.status !== "CANCELLED",
    ) ?? [];
  const [nextEvent, ...laterEvents] = booked;
  const unread = inbox.data?.unreadCount ?? 0;
  const first =
    data?.profile?.details.firstName ||
    account?.displayName.split(" ")[0] ||
    "friend";
  const today = new Date(now);
  const hero = (
    <>
      <View style={{ gap: 4 }}>
        <Text style={[ui.caption, { color: colors.onDarkMuted, fontSize: 13 }]}>
          {new Intl.DateTimeFormat("en-ZA", {
            weekday: "long",
            day: "numeric",
            month: "long",
          }).format(today)}
        </Text>
        <Text
          accessibilityRole="header"
          style={{
            fontFamily: fonts.display,
            fontSize: 32,
            lineHeight: 38,
            letterSpacing: -0.5,
            color: colors.onDark,
          }}
        >
          {greeting(today.getHours())}, {first}
        </Text>
      </View>
      {nextEvent ? (
        <View
          style={{
            backgroundColor: colors.heroCard,
            borderRadius: 24,
            borderWidth: 1,
            borderColor: colors.heroLine,
            padding: 16,
            gap: 14,
          }}
        >
          <Text style={[ui.eyebrow, { color: colors.goldBright }]}>
            YOUR NEXT GATHERING
          </Text>
          <View style={{ flexDirection: "row", gap: 14, alignItems: "center" }}>
            <DateTile
              startsAt={nextEvent.startsAt}
              timeZone={nextEvent.timeZone}
              light
            />
            <View style={{ flex: 1, gap: 4 }}>
              <Text
                numberOfLines={2}
                style={{
                  color: colors.onDark,
                  fontSize: 17,
                  fontWeight: "600",
                  fontFamily: fonts.sans,
                }}
              >
                {nextEvent.title}
              </Text>
              <Text
                numberOfLines={1}
                style={[
                  ui.caption,
                  { color: colors.onDarkMuted, fontSize: 13 },
                ]}
              >
                {eventDay(nextEvent).time} · {nextEvent.venueName}
              </Text>
            </View>
          </View>
          {admissionOpen(nextEvent, now) && (
            <Button
              title="Show attendance QR"
              label="Show my attendance QR"
              variant="gold"
              icon="qr-code-outline"
              onPress={() =>
                router.push({
                  pathname: "/event/[id]",
                  params: { id: nextEvent.id, pass: "1" },
                })
              }
            />
          )}
          <Button
            title="Open my booking"
            label={`View ${nextEvent.title}`}
            variant={admissionOpen(nextEvent, now) ? "secondary" : "gold"}
            icon="ticket-outline"
            onPress={() =>
              router.push({
                pathname: "/event/[id]",
                params: { id: nextEvent.id },
              })
            }
          />
        </View>
      ) : (
        upcoming.data?.verified && (
          <View
            style={{
              backgroundColor: colors.heroCard,
              borderRadius: 24,
              borderWidth: 1,
              borderColor: colors.heroLine,
              padding: 16,
              gap: 10,
            }}
          >
            <Text
              style={[ui.body, { color: colors.onDarkMuted, fontSize: 14 }]}
            >
              Your upcoming bookings will appear here.
            </Text>
            <LinkButton
              light
              title="Find a gathering"
              onPress={() => router.navigate("/events")}
            />
          </View>
        )
      )}
    </>
  );
  return (
    <Screen
      hero={hero}
      refreshing={
        loading ||
        upcoming.loading ||
        meetings.loading ||
        volunteering.loading ||
        inbox.loading
      }
      onRefresh={() => {
        void reload();
        void upcoming.reload();
        void meetings.reload();
        void volunteering.reload();
        void inbox.reload();
      }}
      actions={
        <IconButton
          light
          icon="notifications-outline"
          label={inbox.data ? `My inbox · ${unread} unread` : "My inbox"}
          dot={unread > 0}
          onPress={() => router.navigate("/inbox")}
        />
      }
    >
      <Message text={error} error />
      <Message text={upcoming.error} error />
      {data && !data.profile && (
        <Message text="Let’s connect your account. Ask the church office to verify your member identity to open your personal profile." />
      )}
      <View style={{ flexDirection: "row", gap: 12 }}>
        <QuickAction
          icon="heart-outline"
          title="Give"
          label="Give online"
          onPress={() => router.navigate("/giving")}
        />
        <QuickAction
          icon="calendar-outline"
          title="Events"
          label="Browse events"
          onPress={() => router.navigate("/events")}
        />
        <QuickAction
          icon="hand-left-outline"
          title="Serve"
          label="My volunteering"
          dot={Boolean(nextDuty && nextDuty.invitation?.status === "PENDING")}
          onPress={() => router.navigate("/volunteering")}
        />
        <QuickAction
          icon="document-text-outline"
          title="Forms"
          label="Forms and quizzes"
          onPress={() => router.navigate("/forms")}
        />
        <QuickAction
          icon="flower-outline"
          title="Family"
          label="My children & family ministry"
          onPress={() => router.navigate("/children")}
        />
      </View>

      {Boolean(nextDuty || volunteering.data?.linked || volunteering.error) && (
        <>
          <SectionHeader
            title="Your next duty"
            action={{
              title: "See all",
              label: "All my duties",
              onPress: () => router.navigate("/volunteering"),
            }}
          />
          <Message text={volunteering.error} error />
          {nextDuty ? (
            <DutyCard
              now={now}
              duty={nextDuty}
              respondTitle="Review invitation"
              onRespond={() => router.navigate("/volunteering")}
            />
          ) : (
            volunteering.data?.linked && (
              <Text style={ui.body}>
                Your upcoming duties will appear here.
              </Text>
            )
          )}
        </>
      )}

      {laterEvents.length > 0 && (
        <>
          <SectionHeader
            title="Also coming up"
            action={{
              title: "All",
              label: "View all events",
              onPress: () => router.navigate("/events"),
            }}
          />
          {laterEvents.slice(0, 2).map((event, i) => (
            <Appear key={event.id} index={i}>
              <EventCard event={event} />
            </Appear>
          ))}
        </>
      )}

      <SectionHeader
        title="With your groups"
        action={{
          title: "See all",
          label: "My groups & ministries",
          onPress: () => router.navigate("/groups"),
        }}
      />
      <Message text={meetings.error} error />
      {!meetings.data && !meetings.error && <SkeletonList count={1} />}
      {meetings.data?.items.map((meeting, i) => (
        <Appear key={meeting.id} index={i}>
          <MeetingCard meeting={meeting} />
        </Appear>
      ))}
      {meetings.data?.verified && !meetings.data.items.length && (
        <Text style={ui.body}>
          Your upcoming group meetings will appear here.
        </Text>
      )}

      <SectionHeader title="Your account" />
      <View style={ui.group}>
        <ListRow
          icon="person-outline"
          title="My personal details"
          subtitle="Keep your church profile up to date"
          onPress={() => router.navigate("/profile")}
        />
        <ListRow
          last
          icon="shield-checkmark-outline"
          title="Account & devices"
          subtitle="Password and signed-in devices"
          onPress={() => router.navigate("/security")}
        />
      </View>
    </Screen>
  );
}
