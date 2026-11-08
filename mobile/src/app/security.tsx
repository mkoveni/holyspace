import { useState } from "react";
import { router } from "expo-router";
import { Text, View } from "react-native";
import type { Device } from "@/core/models";
import { useSession } from "@/core/session";
import { date } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import {
  Avatar,
  Badge,
  Button,
  Field,
  IconTile,
  ListRow,
  Message,
  PageHeader,
  Pager,
  InfoLink,
  Screen,
  SectionHeader,
  Sheet,
} from "@/ui/components";
import { colors, ui } from "@/ui/theme";
export default function Security() {
  const session = useSession(),
    [offset, setOffset] = useState(0),
    { data, loading, error, reload } = useLoad(
      () =>
        session.api<{ items: Device[]; hasMore: boolean }>(
          `/api/me/sessions?limit=25&offset=${offset}`,
        ),
      [offset],
    );
  const [busy, setBusy] = useState(false),
    [actionError, setActionError] = useState(""),
    [selected, setSelected] = useState<Device | null>(null),
    [passwordOpen, setPasswordOpen] = useState(false),
    [current, setCurrent] = useState(""),
    [password, setPassword] = useState(""),
    [confirm, setConfirm] = useState(""),
    [signOutOpen, setSignOutOpen] = useState(false);
  async function revoke() {
    if (!selected || busy) return;
    setBusy(true);
    setActionError("");
    try {
      await session.api(`/api/me/sessions/${selected.id}`, "DELETE");
      if (selected.current)
        await session.clear("This device has been signed out.");
      else {
        setSelected(null);
        await reload();
      }
    } catch (e) {
      setActionError(
        e instanceof Error ? e.message : "Unable to revoke this device.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function change() {
    if (busy || !current || password.length < 12 || password !== confirm)
      return;
    setBusy(true);
    setActionError("");
    try {
      await session.api("/api/me/password", "PUT", {
        currentPassword: current,
        password,
      });
      setCurrent("");
      setPassword("");
      setConfirm("");
      await session.clear(
        "Password changed. Sign in again with your new password.",
      );
    } catch (e) {
      setActionError(
        e instanceof Error ? e.message : "Unable to change password.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function signOut() {
    setBusy(true);
    try {
      await session.signOut();
    } catch {
      await session.clear(
        "Signed out on this device. If you were offline, use another signed-in device to revoke this session.",
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
    >
      <PageHeader
        eyebrow="Your account, protected"
        title="Peace of mind. In your hands."
        intro="Manage your password and the devices signed in to your account."
      />
      <Message text={error} error />
      {!selected && !passwordOpen && <Message text={actionError} error />}
      <View style={ui.group}>
        <View style={[ui.row, { justifyContent: "flex-start", padding: 16 }]}>
          <Avatar name={session.account?.displayName || "Account"} size={48} />
          <View style={{ flex: 1, gap: 2 }}>
            <Text style={ui.h3}>{session.account?.displayName}</Text>
            <Text style={ui.caption}>{session.account?.email}</Text>
          </View>
        </View>
        <View style={{ height: 1, backgroundColor: colors.line }} />
        <ListRow
          icon="key-outline"
          title="Change my password"
          subtitle="Signs out every device"
          onPress={() => {
            setActionError("");
            setCurrent("");
            setPassword("");
            setConfirm("");
            setPasswordOpen(true);
          }}
        />
        <ListRow
          last
          icon="log-out-outline"
          tone="danger"
          title="Sign out"
          onPress={() => setSignOutOpen(true)}
        />
      </View>
      <SectionHeader title="Signed-in devices" />
      <Text style={[ui.body, { fontSize: 14, marginTop: -8 }]}>
        Know where you’re connected.
      </Text>
      {data && data.items.length > 0 && (
        <View style={ui.group}>
          {data.items.map((d, i) => (
            <View
              key={d.id}
              style={{
                gap: 8,
                padding: 16,
                borderBottomWidth: i === data.items.length - 1 ? 0 : 1,
                borderColor: colors.line,
              }}
            >
              <View style={[ui.row, { justifyContent: "flex-start" }]}>
                <IconTile
                  size={38}
                  icon={d.current ? "phone-portrait" : "phone-portrait-outline"}
                  tone={d.current ? "success" : "neutral"}
                />
                <Text style={[ui.h3, { flex: 1, fontSize: 15 }]}>
                  {d.deviceName}
                </Text>
                {d.current && <Badge text="This device" tone="success" />}
              </View>
              <Text style={ui.caption}>
                Signed in {date(d.createdAt)} · Expires {date(d.expiresAt)}
              </Text>
              <Button
                title={
                  d.current ? "Sign out this device" : "Revoke device access"
                }
                variant="ghost"
                style={{
                  alignSelf: "flex-start",
                  minHeight: 40,
                  paddingVertical: 8,
                  paddingHorizontal: 0,
                }}
                onPress={() => {
                  setActionError("");
                  setSelected(d);
                }}
              />
            </View>
          ))}
        </View>
      )}
      <InfoLink
        sheetTitle="About signed-in devices"
        paragraphs={[
          "Device names are labels supplied at sign-in.",
          "Revoke sessions you do not recognize. Sessions expire automatically.",
        ]}
      />
      <Pager
        hasPrevious={offset > 0}
        hasNext={Boolean(data?.hasMore)}
        disabled={loading}
        previous={{
          title: "Previous devices",
          onPress: () => setOffset(Math.max(0, offset - 25)),
        }}
        next={{
          title: "More devices",
          onPress: () => setOffset(offset + 25),
        }}
      />
      <Sheet
        title="Revoke this device?"
        visible={Boolean(selected)}
        onClose={() => !busy && setSelected(null)}
      >
        <Message text={actionError} error />
        <Text style={ui.body}>
          {selected?.deviceName} will lose access immediately. The account can
          sign in again with its password.
        </Text>
        <Button
          title="Confirm device revocation"
          danger
          busy={busy}
          onPress={() => void revoke()}
        />
      </Sheet>
      <Sheet
        title="Change your password"
        visible={passwordOpen}
        onClose={() => !busy && setPasswordOpen(false)}
      >
        <Message text={actionError} error />
        <Field
          label="Current password"
          value={current}
          onChangeText={setCurrent}
          secureTextEntry
          editable={!busy}
        />
        <Field
          label="New password"
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          editable={!busy}
        />
        <Field
          label="Confirm new password"
          value={confirm}
          onChangeText={setConfirm}
          secureTextEntry
          editable={!busy}
        />
        <Text style={ui.body}>
          Use at least 12 characters. All devices, including this one, will be
          signed out.
        </Text>
        <Button
          title="Update password"
          busy={busy}
          disabled={!current || password.length < 12 || password !== confirm}
          onPress={() => void change()}
        />
      </Sheet>
      <Sheet
        title="Sign out of Holyspace?"
        visible={signOutOpen}
        onClose={() => !busy && setSignOutOpen(false)}
      >
        <Text style={ui.body}>
          Your personal information will be cleared from this device.
        </Text>
        <Button
          title="Confirm sign out"
          busy={busy}
          onPress={() => void signOut()}
        />
      </Sheet>
    </Screen>
  );
}
