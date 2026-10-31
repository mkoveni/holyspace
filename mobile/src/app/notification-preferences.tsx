import { PushPreferences } from "@/ui/push-preferences";
import { useState } from "react";
import { Text, View } from "react-native";
import { router } from "expo-router";
import type { NotificationPreference } from "@/core/models";
import { notificationTopic } from "@/core/notifications";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen } from "@/ui/components";
import { ui } from "@/ui/theme";
export default function NotificationPreferences() {
  const { api } = useSession();
  const { data, error, loading, reload } = useLoad(() =>
    api<NotificationPreference[]>("/api/member/notification-preferences"),
  );
  const [busy, setBusy] = useState(false),
    [failure, setFailure] = useState("");
  async function toggle(item: NotificationPreference) {
    if (busy) return;
    setBusy(true);
    setFailure("");
    try {
      await api(`/api/member/notification-preferences/${item.topic}`, "PUT", {
        enabled: !item.enabled,
        expectedVersion: item.version,
      });
      await reload();
    } catch (e) {
      setFailure(
        e instanceof Error
          ? e.message
          : "Unable to save your preference. Refresh and try again.",
      );
      await reload();
    } finally {
      setBusy(false);
    }
  }
  return (
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <Button
        title="Back to inbox"
        subtle
        onPress={() => router.navigate("/inbox")}
      />
      <Text style={ui.eyebrow}>MAKE IT YOURS</Text>
      <Text style={ui.title}>Notification preferences</Text>
      <Text style={ui.body}>
        Choose which future updates appear in your inbox.
      </Text>
      <Message text={error || failure} error />
      {data?.map((item) => (
        <View key={item.topic} style={[ui.card, { gap: 14 }]}>
          <Text style={ui.h2}>{notificationTopic(item.topic)}</Text>
          <Text style={ui.body}>
            {item.topic === "VOLUNTEERING"
              ? "Duty assignments, plan changes and invitations to serve."
              : "Updates when you book or cancel an event in the app."}
          </Text>
          <Text style={ui.eyebrow}>
            {item.enabled ? "Inbox updates on" : "Inbox updates off"}
          </Text>
          <Button
            title={`${item.enabled ? "Turn off" : "Turn on"} ${notificationTopic(item.topic)}`}
            subtle
            disabled={busy || loading}
            onPress={() => void toggle(item)}
          />
        </View>
      ))}
      <Message text="Changes apply to future inbox notices. Your existing updates, bookings and duties stay available. Email and device push preferences are separate." />
      <PushPreferences />
    </Screen>
  );
}
