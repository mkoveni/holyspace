import { Pressable, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { router } from "expo-router";
import type { MemberEvent } from "@/core/models";
import { eventDay } from "@/core/events";
import { label } from "@/core/money";
import { Badge } from "./components";
import { CoverThumb } from "./cover-image";
import type { Tone } from "./components";
import { colors, fonts, ui } from "./theme";

export function DateTile({
  startsAt,
  timeZone,
  light = false,
}: {
  startsAt: string;
  timeZone: string;
  light?: boolean;
}) {
  const d = eventDay({ startsAt, timeZone });
  return (
    <View
      style={{
        width: 56,
        paddingVertical: 8,
        borderRadius: 16,
        alignItems: "center",
        backgroundColor: light ? "rgba(255,255,255,0.08)" : colors.goldSoft,
        borderWidth: light ? 1 : 0,
        borderColor: colors.heroLine,
      }}
    >
      <Text
        style={{
          fontSize: 10,
          fontWeight: "700",
          letterSpacing: 1.2,
          color: light ? colors.goldBright : colors.gold,
          fontFamily: fonts.sans,
        }}
      >
        {d.month.toUpperCase()}
      </Text>
      <Text
        style={{
          fontFamily: fonts.display,
          fontSize: 24,
          lineHeight: 28,
          color: light ? colors.onDark : colors.ink,
        }}
      >
        {d.day}
      </Text>
      <Text
        style={{
          fontSize: 10,
          fontWeight: "600",
          color: light ? colors.onDarkMuted : colors.muted,
          fontFamily: fonts.sans,
        }}
      >
        {d.weekday}
      </Text>
    </View>
  );
}

export function eventStatus(event: MemberEvent): { text: string; tone: Tone } {
  if (event.status === "CANCELLED")
    return { text: "Gathering cancelled", tone: "danger" };
  if (event.booking) {
    if (event.booking.attendance === "PRESENT")
      return { text: "Checked in", tone: "success" };
    const s = event.booking.status;
    return {
      text: label(s).replace(/^./, (c) => c.toUpperCase()),
      tone:
        s === "CONFIRMED"
          ? "success"
          : s === "CANCELLED"
            ? "danger"
            : s === "WAITLISTED"
              ? "gold"
              : "neutral",
    };
  }
  if (!event.registrationOpen)
    return { text: "Registration closed", tone: "neutral" };
  if (event.remainingPlaces === 0)
    return event.waitlistEnabled
      ? { text: "Waitlist available", tone: "gold" }
      : { text: "Fully booked", tone: "warning" };
  return { text: "Registration open", tone: "neutral" };
}

export function EventCard({ event }: { event: MemberEvent }) {
  const status = eventStatus(event);
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`View ${event.title}`}
      onPress={() =>
        router.push({ pathname: "/event/[id]", params: { id: event.id } })
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
      <CoverThumb
        url={event.coverImageUrl}
        size={56}
        fallback={
          <DateTile startsAt={event.startsAt} timeZone={event.timeZone} />
        }
      />
      <View style={{ flex: 1, gap: 4 }}>
        <Text style={ui.h3} numberOfLines={2}>
          {event.title}
        </Text>
        <Text style={ui.caption} numberOfLines={1}>
          {eventDay(event).time} · {event.venueName}
        </Text>
        <View style={{ marginTop: 4 }}>
          <Badge text={status.text} tone={status.tone} />
        </View>
      </View>
      <Ionicons name="chevron-forward" size={18} color={colors.faint} />
    </Pressable>
  );
}
