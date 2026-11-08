import { useState } from "react";
import { Text, View } from "react-native";
import { useSession } from "@/core/session";
import { appName } from "@/core/brand";
import { useDevicePush } from "@/core/device-push";
import type { PushSettings } from "@/core/push";
import { notificationTopic } from "@/core/notifications";
import { useLoad } from "@/hooks/use-load";
import {
  Button,
  InfoLink,
  Message,
  SectionHeader,
  ToggleRow,
} from "./components";
import { ui } from "./theme";
export function PushPreferences() {
  const { api } = useSession();
  const push = useDevicePush();
  const { data, loading, error, reload } = useLoad(
    () => api<PushSettings>("/api/member/push"),
    [push.revision],
  );
  const [busy, setBusy] = useState(false),
    [failure, setFailure] = useState("");
  async function action(task: () => Promise<unknown>) {
    if (busy || push.busy) return;
    setBusy(true);
    setFailure("");
    try {
      await task();
    } catch (e) {
      setFailure(
        e instanceof Error ? e.message : "Unable to update push settings.",
      );
    } finally {
      await reload();
      setBusy(false);
    }
  }
  return (
    <View style={{ gap: 16 }}>
      <SectionHeader title="On this device" />
      <Text style={[ui.body, { fontSize: 14, marginTop: -6 }]}>
        A gentle heads-up, even when {appName} is closed. Personal details stay
        inside the app.
      </Text>
      <Message text={failure || error} error />
      {!push.supported && (
        <Message text="Device push is available in the Android and iOS app. Your browser preview keeps the inbox available." />
      )}
      {data && !data.available && (
        <Message text="Your church is finishing device push setup. Your inbox remains available." />
      )}
      {push.supported && (
        <>
          <Button
            title="Enable notifications on this device"
            icon="notifications-outline"
            disabled={busy || push.busy || loading || !data?.available}
            onPress={() => void action(push.enable)}
          />
          <Button
            title="Disable notifications on this device"
            variant="ghost"
            disabled={busy || push.busy}
            onPress={() => void action(push.disable)}
          />
        </>
      )}
      {data?.preferences && data.preferences.length > 0 && (
        <View style={ui.group}>
          {data.preferences.map((item, i, all) => (
            <ToggleRow
              key={item.topic}
              last={i === all.length - 1}
              title={notificationTopic(item.topic)}
              status={`Push alerts ${item.enabled ? "on" : "off"}`}
              on={item.enabled}
              actionLabel={`${item.enabled ? "Turn off" : "Turn on"} push for ${notificationTopic(item.topic)}`}
              disabled={busy || push.busy || loading}
              onPress={() =>
                void action(() =>
                  api(`/api/member/push/preferences/${item.topic}`, "PUT", {
                    enabled: !item.enabled,
                    expectedVersion: item.version,
                  }),
                )
              }
            />
          ))}
        </View>
      )}
      {data?.devices?.map((device) => (
        <View key={device.id} style={[ui.card, { gap: 12 }]}>
          <Text style={ui.h3}>
            {device.platform === "ios" ? "iOS" : "Android"} device
          </Text>
          <Text style={ui.body}>
            {device.currentSession
              ? "Registered with this sign-in"
              : "Another sign-in"}{" "}
            · {device.status.toLowerCase()}
          </Text>
          {device.status === "ACTIVE" && (
            <Button
              title="Revoke this push registration"
              variant="ghost"
              disabled={busy}
              onPress={() =>
                void action(() =>
                  api(`/api/member/push/devices/${device.id}`, "DELETE"),
                )
              }
            />
          )}
        </View>
      ))}
      <InfoLink
        sheetTitle="About push notifications"
        paragraphs={[
          "Enable this device and choose the topics you want. Push choices are separate from inbox and email settings.",
          "Signing out or revoking a session stops future sends once the server receives it; a notification already handed to the provider may still arrive.",
        ]}
      />
    </View>
  );
}
