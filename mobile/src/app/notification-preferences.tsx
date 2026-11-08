import { PushPreferences } from "@/ui/push-preferences";
import { useState } from "react";
import { View } from "react-native";
import { router } from "expo-router";
import type { NotificationPreference } from "@/core/models";
import { notificationTopic } from "@/core/notifications";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import {
  Message,
  PageHeader,
  InfoLink,
  Screen,
  SectionHeader,
  ToggleRow,
} from "@/ui/components";
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
    <Screen
      refreshing={loading}
      onRefresh={() => void reload()}
      back={{
        label: "Back to inbox",
        onPress: () => router.navigate("/inbox"),
      }}
    >
      <PageHeader
        eyebrow="Make it yours"
        title="Notification preferences"
        intro="Choose which future updates appear in your inbox."
      />
      <Message text={error || failure} error />
      <SectionHeader title="In your inbox" />
      {data && data.length > 0 && (
        <View style={ui.group}>
          {data.map((item, i) => (
            <ToggleRow
              key={item.topic}
              last={i === data.length - 1}
              title={notificationTopic(item.topic)}
              description={
                item.topic === "VOLUNTEERING"
                  ? "Duty assignments, plan changes and invitations to serve."
                  : "Updates when you book or cancel an event in the app."
              }
              status={item.enabled ? "Inbox updates on" : "Inbox updates off"}
              on={item.enabled}
              actionLabel={`${item.enabled ? "Turn off" : "Turn on"} ${notificationTopic(item.topic)}`}
              disabled={busy || loading}
              onPress={() => void toggle(item)}
            />
          ))}
        </View>
      )}
      <InfoLink
        sheetTitle="About inbox preferences"
        paragraphs={[
          "Changes apply to future inbox notices. Your existing updates, bookings and duties stay available.",
          "Email and device push preferences are separate.",
        ]}
      />
      <PushPreferences />
    </Screen>
  );
}
