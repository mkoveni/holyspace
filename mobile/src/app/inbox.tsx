import { useDevicePush } from "@/core/device-push";
import { useState } from "react";
import { Text, View } from "react-native";
import { router } from "expo-router";
import type { MemberInbox, MemberNotification } from "@/core/models";
import {
  notificationDestination,
  notificationTopic,
  unreadLabel,
} from "@/core/notifications";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import {
  Appear,
  Badge,
  Button,
  EmptyState,
  IconButton,
  Message,
  PageHeader,
  Pager,
  InfoLink,
  Screen,
  Segmented,
} from "@/ui/components";
import { colors, ui } from "@/ui/theme";
import { SkeletonList } from "@/ui/skeleton";
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
    <Screen
      refreshing={loading}
      onRefresh={() => void reload()}
      back={{ label: "Back to home", onPress: () => router.navigate("/") }}
      actions={
        <IconButton
          icon="options-outline"
          label="Notification preferences"
          onPress={() => router.push("/notification-preferences")}
        />
      }
    >
      <PageHeader
        eyebrow="Stay connected"
        title="Your inbox"
        intro="Invitations and little updates, all in one place."
      />
      <Segmented
        value={unreadOnly ? "unread" : "all"}
        disabled={busy}
        options={[
          { value: "all", label: "All updates" },
          { value: "unread", label: "Unread" },
        ]}
        onChange={(v) => {
          setUnreadOnly(v === "unread");
          setOffset(0);
        }}
      />
      <Message text={error || failure} error />
      {!data && !error && <SkeletonList count={2} />}
      {data && <Text style={ui.eyebrow}>{unreadLabel(data.unreadCount)}</Text>}
      {data && !data.items.length && (
        <EmptyState
          icon="mail-open-outline"
          title={unreadOnly ? "You’re all caught up" : "A little quiet here"}
          text={
            offset
              ? "No updates on this page. Go back to the previous page."
              : "New invitations and updates will appear here. Your duties and bookings are always available in their own screens."
          }
        />
      )}
      {data?.items.map((item, index) => {
        const path = notificationDestination(item.destination);
        const unread = !item.readAt;
        return (
          <Appear key={item.id} index={index}>
            <View
              style={[
                ui.card,
                { gap: 12 },
                unread && { borderColor: colors.goldBright },
              ]}
            >
              <View style={ui.row}>
                <Text style={[ui.eyebrow, { flex: 1 }]}>
                  {notificationTopic(item.topic)}
                </Text>
                {unread && <Badge text="New" tone="gold" />}
              </View>
              <Text style={ui.h2}>{item.title}</Text>
              <Text style={ui.body}>{item.message}</Text>
              <Text style={ui.caption}>
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
                variant={path ? "ghost" : "secondary"}
                icon={item.readAt ? "mail-unread-outline" : "checkmark-done"}
                disabled={busy || loading}
                onPress={() => void mark(item, !item.readAt)}
              />
            </View>
          </Appear>
        );
      })}
      {data && (
        <Pager
          hasPrevious={offset > 0}
          hasNext={data.hasMore}
          disabled={busy || loading}
          previous={{
            title: "Previous updates",
            onPress: () => setOffset(Math.max(0, offset - 10)),
          }}
          next={{
            title: "More updates",
            onPress: () => setOffset(offset + 10),
          }}
        />
      )}
      <InfoLink
        sheetTitle="About your inbox"
        paragraphs={[
          "Open an update to review the latest information.",
          "An inbox notice does not grant access or confirm attendance, readiness or pickup permission.",
        ]}
      />
    </Screen>
  );
}
