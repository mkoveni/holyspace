import { useState } from "react";
import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { useSession } from "@/core/session";
import type { MemberEvents } from "@/core/models";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen } from "@/ui/components";
import { EventCard } from "@/ui/event-card";
import { colors, ui } from "@/ui/theme";
export default function EventsScreen() {
  const { api } = useSession();
  const [mine, setMine] = useState(false),
    [offset, setOffset] = useState(0);
  const { data, loading, error, reload } = useLoad(
    () =>
      api<MemberEvents>(
        `/api/member/events?mine=${mine}&limit=25&offset=${offset}`,
      ),
    [mine, offset],
  );
  function choose(value: boolean) {
    setOffset(0);
    setMine(value);
  }
  return (
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <View style={{ gap: 10 }}>
        <Text style={ui.eyebrow}>GATHER. BELONG. GROW.</Text>
        <Text style={ui.title}>Make room for community.</Text>
        <Text style={ui.body}>
          Find your next gathering and keep your plans close. Upcoming six
          months.
        </Text>
      </View>
      <View style={ui.row}>
        <Button
          title="Discover events"
          subtle={mine}
          onPress={() => choose(false)}
        />
        <Button
          title="My bookings"
          subtle={!mine}
          onPress={() => choose(true)}
        />
      </View>
      <Message text={error} error />
      {data && !data.verified ? (
        <View style={ui.card}>
          <Ionicons name="lock-closed-outline" size={30} color={colors.green} />
          <Text style={ui.h2}>Let’s connect your events.</Text>
          <Text style={ui.body}>
            Ask the church office to verify your member identity before browsing
            or booking events.
          </Text>
        </View>
      ) : (
        data && (
          <>
            {!data.items.length && (
              <View style={ui.card}>
                <Ionicons
                  name="calendar-outline"
                  size={30}
                  color={colors.green}
                />
                <Text style={ui.h2}>
                  {mine
                    ? "Your next chapter awaits."
                    : "More moments are on their way."}
                </Text>
                <Text style={ui.body}>
                  {mine
                    ? "You have no upcoming bookings. Explore the church calendar to find your next gathering."
                    : "There are no published gatherings in the next six months. Check back soon."}
                </Text>
              </View>
            )}
            {data.items.map((event) => (
              <EventCard key={event.id} event={event} />
            ))}
            {(offset > 0 || data.hasMore) && (
              <View style={ui.row}>
                <Button
                  title="Previous events"
                  subtle
                  disabled={offset === 0 || loading}
                  onPress={() => setOffset(Math.max(0, offset - 25))}
                />
                <Button
                  title="More events"
                  subtle
                  disabled={!data.hasMore || loading}
                  onPress={() => setOffset(offset + 25)}
                />
              </View>
            )}
          </>
        )
      )}
    </Screen>
  );
}
