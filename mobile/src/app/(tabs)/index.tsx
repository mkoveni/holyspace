import { useDevicePush } from "@/core/device-push";
import { useEffect, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { router } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import type {
  MemberInbox,
  MemberProfile,
  MemberEvents,
  MyMeetings,
  MyVolunteering,
} from "@/core/models";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen } from "@/ui/components";
import { MeetingCard } from "@/ui/meeting-card";
import { DutyCard } from "@/ui/duty-card";
import { EventCard } from "@/ui/event-card";
import { colors, ui } from "@/ui/theme";
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
  const first =
    data?.profile?.details.firstName ||
    account?.displayName.split(" ")[0] ||
    "friend";
  return (
    <Screen
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
    >
      <View style={{ gap: 9 }}>
        <Text style={ui.eyebrow}>YOUR CHURCH, YOUR COMMUNITY</Text>
        <Text style={ui.title}>Welcome home, {first}.</Text>
        <Text style={ui.body}>A little space for what matters most.</Text>
      </View>
      <Message text={error} error />
      {data && !data.profile && (
        <Message text="Let’s connect your account. Ask the church office to verify your member identity to open your personal profile." />
      )}
      <Button
        title={
          inbox.data
            ? `My inbox · ${inbox.data.unreadCount} unread`
            : "My inbox"
        }
        subtle
        onPress={() => router.navigate("/inbox")}
      />
      <LinearGradient
        colors={["#244B3C", "#376B50"]}
        style={{ padding: 26, borderRadius: 25, gap: 18 }}
      >
        <Ionicons name="heart-outline" color="#DFE5D7" size={32} />
        <Text style={[ui.h2, { color: "#fff", fontSize: 25 }]}>
          Small acts. Lasting impact.
        </Text>
        <Text style={[ui.body, { color: "#E0E6D8" }]}>
          Your generosity helps our community grow.
        </Text>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Give online"
          onPress={() => router.navigate("/giving")}
          style={{
            backgroundColor: "#F0F1DD",
            padding: 15,
            borderRadius: 14,
            alignSelf: "flex-start",
          }}
        >
          <Text style={{ fontWeight: "600", color: colors.dark }}>
            Make a contribution ↗
          </Text>
        </Pressable>
      </LinearGradient>
      <View style={ui.row}>
        <Text style={ui.eyebrow}>COMING UP FOR YOU</Text>
        <Button
          title="View all events"
          subtle
          onPress={() => router.navigate("/events")}
        />
      </View>
      <Message text={upcoming.error} error />
      {upcoming.data?.items
        .filter((event) => event.booking?.status !== "CANCELLED")
        .slice(0, 3)
        .map((event) => (
          <EventCard key={event.id} event={event} />
        ))}
      {upcoming.data?.verified &&
        !upcoming.data.items.some(
          (event) => event.booking?.status !== "CANCELLED",
        ) && (
          <Text style={ui.body}>Your upcoming bookings will appear here.</Text>
        )}
      <View style={ui.row}>
        <Text style={ui.eyebrow}>WITH YOUR GROUPS</Text>
        <Button
          title="My groups & ministries"
          subtle
          onPress={() => router.navigate("/groups")}
        />
      </View>
      <Message text={meetings.error} error />
      {meetings.data?.items.map((meeting) => (
        <MeetingCard key={meeting.id} meeting={meeting} />
      ))}
      {meetings.data?.verified && !meetings.data.items.length && (
        <Text style={ui.body}>
          Your upcoming group meetings will appear here.
        </Text>
      )}
      <View style={ui.row}>
        <Text style={ui.eyebrow}>YOUR NEXT DUTY</Text>
        <Button
          title="My volunteering"
          subtle
          onPress={() => router.navigate("/volunteering")}
        />
      </View>
      <Message text={volunteering.error} error />
      {nextDuty && (
        <DutyCard
          now={now}
          duty={nextDuty}
          onRespond={() => router.navigate("/volunteering")}
        />
      )}
      {volunteering.data?.linked && !nextDuty && (
        <Text style={ui.body}>Your upcoming duties will appear here.</Text>
      )}
      <Text style={ui.eyebrow}>YOUR DIVINE COMMUNITY SPACE</Text>
      {(
        [
          {
            title: "My children & family ministry",
            subtitle: "Programmes, preparation and safe collection",
            icon: "flower-outline",
            path: "/children",
          },
          {
            title: "My personal details",
            subtitle: "Keep your church profile up to date",
            icon: "person-outline",
            path: "/profile",
          },
          {
            title: "My giving",
            subtitle: "Give online and follow your contributions",
            icon: "heart-outline",
            path: "/giving",
          },
          {
            title: "Account & devices",
            subtitle: "Manage your password and signed-in devices",
            icon: "shield-checkmark-outline",
            path: "/security",
          },
        ] as const
      ).map((item) => (
        <Pressable
          key={item.path}
          accessibilityRole="button"
          accessibilityLabel={item.title}
          onPress={() => router.navigate(item.path)}
          style={[ui.card, ui.row, { padding: 20 }]}
        >
          <View
            style={{
              padding: 12,
              borderRadius: 14,
              backgroundColor: colors.pale,
            }}
          >
            <Ionicons name={item.icon} size={23} color={colors.green} />
          </View>
          <View style={{ flex: 1, gap: 5 }}>
            <Text
              style={{ color: colors.text, fontSize: 15, fontWeight: "600" }}
            >
              {item.title}
            </Text>
            <Text style={{ color: colors.muted, fontSize: 12, lineHeight: 18 }}>
              {item.subtitle}
            </Text>
          </View>
          <Ionicons name="chevron-forward" size={16} color={colors.muted} />
        </Pressable>
      ))}
    </Screen>
  );
}
