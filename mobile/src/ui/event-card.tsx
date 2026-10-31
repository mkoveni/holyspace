import { Pressable, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { router } from "expo-router";
import type { MemberEvent } from "@/core/models";
import { eventTime } from "@/core/events";
import { label } from "@/core/money";
import { colors, ui } from "./theme";
export function EventCard({ event }: { event: MemberEvent }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`View ${event.title}`}
      onPress={() =>
        router.push({ pathname: "/event/[id]", params: { id: event.id } })
      }
      style={ui.card}
    >
      <View style={ui.row}>
        <Text style={[ui.eyebrow, { flex: 1 }]}>{eventTime(event)}</Text>
        <Ionicons name="arrow-forward" size={20} color={colors.green} />
      </View>
      <Text style={ui.h2}>{event.title}</Text>
      <Text style={ui.body}>
        {event.venueName} · {event.timeZone}
      </Text>
      <View
        style={[ui.row, { justifyContent: "flex-start", flexWrap: "wrap" }]}
      >
        {event.status === "CANCELLED" && (
          <Text style={ui.badge}>Gathering cancelled</Text>
        )}
        {event.booking && (
          <Text style={ui.badge}>
            {event.booking.attendance === "PRESENT"
              ? "Checked in"
              : label(event.booking.status)}
          </Text>
        )}
        {!event.booking && (
          <Text style={ui.badge}>
            {event.registrationOpen
              ? event.remainingPlaces === 0
                ? event.waitlistEnabled
                  ? "Waitlist available"
                  : "Fully booked"
                : "Registration open"
              : "Registration closed"}
          </Text>
        )}
      </View>
    </Pressable>
  );
}
