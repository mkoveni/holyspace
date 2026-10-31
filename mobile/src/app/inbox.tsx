import { useDevicePush } from "@/core/device-push";
import { useState } from "react";
import { Text, View } from "react-native";
import { router } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import type { MemberInbox, MemberNotification } from "@/core/models";
import {
  notificationDestination,
  notificationTopic,
  unreadLabel,
} from "@/core/notifications";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen } from "@/ui/components";
import { colors, ui } from "@/ui/theme";
export default function Inbox() {
  const { api } = useSession();
  const push = useDevicePush();
  const [offset, setOffset] = useState(0);
  const [unreadOnly, setUnreadOnly] = useState(false);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState("");
  const { data, loading, error, reload } = useLoad(
    (key) => {
      const [page, unread] = JSON.parse(key) as [number, boolean];
      return api<MemberInbox>(
        `/api/member/notifications?limit=10&offset=${page}&unreadOnly=${unread}`,
      );
    },
    [offset, unreadOnly, push.revision],
  );
  async function mark(item: MemberNotification, read: boolean) {
    if (busy) return;
    setBusy(true);
    setFailure("");
    try {
      await api(`/api/member/notifications/${item.id}/read`, "PUT", { read });
      await reload();
    } catch (e) {
      setFailure(
        e instanceof Error
          ? e.message
          : "Unable to update your notification. Refresh before trying again.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <Button
        title="Back to home"
        subtle
        onPress={() => router.navigate("/")}
      />
      <View style={ui.row}>
        <View style={{ flex: 1, gap: 8 }}>
          <Text style={ui.eyebrow}>STAY CONNECTED</Text>
          <Text style={ui.title}>Your inbox</Text>
        </View>
        <Ionicons name="notifications-outline" size={32} color={colors.green} />
      </View>
      <Text style={ui.body}>
        Invitations and little updates, all in one place.
      </Text>
      <Button
        title="Notification preferences"
        subtle
        onPress={() => router.push("/notification-preferences")}
      />
      <View style={ui.row}>
        <Button
          title="All updates"
          subtle={unreadOnly}
          disabled={busy}
          onPress={() => {
            setUnreadOnly(false);
            setOffset(0);
          }}
        />
        <Button
          title="Unread"
          subtle={!unreadOnly}
          disabled={busy}
          onPress={() => {
            setUnreadOnly(true);
            setOffset(0);
          }}
        />
      </View>
      <Message text={error || failure} error />
      {data && <Text style={ui.eyebrow}>{unreadLabel(data.unreadCount)}</Text>}
      {data && !data.items.length && (
        <View style={[ui.card, { gap: 14 }]}>
          <Ionicons name="mail-open-outline" size={32} color={colors.green} />
          <Text style={ui.h2}>
            {unreadOnly ? "You’re all caught up" : "A little quiet here"}
          </Text>
          <Text style={ui.body}>
            {offset
              ? "No updates on this page. Go back to the previous page."
              : "New invitations and updates will appear here. Your duties and bookings are always available in their own screens."}
          </Text>
        </View>
      )}
      {data?.items.map((item) => {
        const path = notificationDestination(item.destination);
        return (
          <View
            key={item.id}
            style={[
              ui.card,
              {
                gap: 14,
                borderColor: item.readAt ? colors.line : colors.green,
              },
            ]}
          >
            <View style={ui.row}>
              <Text style={[ui.eyebrow, { flex: 1 }]}>
                {notificationTopic(item.topic)}
              </Text>
              {!item.readAt && (
                <Text style={{ color: colors.green, fontWeight: "700" }}>
                  NEW
                </Text>
              )}
            </View>
            <Text style={ui.h2}>{item.title}</Text>
            <Text style={ui.body}>{item.message}</Text>
            <Text style={{ color: colors.muted, fontSize: 12 }}>
              {new Date(item.createdAt).toLocaleString()}
            </Text>
            {path && (
              <Button
                title="View current details"
                disabled={busy || loading}
                onPress={() => router.navigate(path)}
              />
            )}
            <Button
              title={item.readAt ? "Mark unread" : "Mark as read"}
              subtle
              disabled={busy || loading}
              onPress={() => void mark(item, !item.readAt)}
            />
          </View>
        );
      })}
      {data && (
        <View style={ui.row}>
          <Button
            title="Previous updates"
            subtle
            disabled={busy || loading || offset === 0}
            onPress={() => setOffset(Math.max(0, offset - 10))}
          />
          <Button
            title="More updates"
            subtle
            disabled={busy || loading || !data.hasMore}
            onPress={() => setOffset(offset + 10)}
          />
        </View>
      )}
      <Message text="Open an update to review the latest information. An inbox notice does not grant access or confirm attendance, readiness or pickup permission." />
    </Screen>
  );
}
