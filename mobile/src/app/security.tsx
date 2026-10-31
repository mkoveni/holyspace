import { useState } from "react";
import { router } from "expo-router";
import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import type { Device } from "@/core/models";
import { useSession } from "@/core/session";
import { date } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import { Button, Field, Message, Screen, Sheet } from "@/ui/components";
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
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <Button
        title="Back to home"
        subtle
        onPress={() => router.navigate("/")}
      />
      <View style={{ gap: 10 }}>
        <Text style={ui.eyebrow}>YOUR ACCOUNT, PROTECTED</Text>
        <Text style={ui.title}>Peace of mind. In your hands.</Text>
        <Text style={ui.body}>
          Manage your password and the devices signed in to your account.
        </Text>
      </View>
      <Message text={error} error />
      {!selected && !passwordOpen && <Message text={actionError} error />}
      <View style={ui.card}>
        <View style={ui.row}>
          <Text style={ui.h2}>{session.account?.displayName}</Text>
          <Ionicons
            name="shield-checkmark-outline"
            size={28}
            color={colors.green}
          />
        </View>
        <Text style={ui.body}>{session.account?.email}</Text>
        <Button
          title="Change my password"
          subtle
          onPress={() => {
            setActionError("");
            setCurrent("");
            setPassword("");
            setConfirm("");
            setPasswordOpen(true);
          }}
        />
        <Button title="Sign out" subtle onPress={() => setSignOutOpen(true)} />
      </View>
      <View style={ui.card}>
        <Text style={ui.eyebrow}>SIGNED-IN DEVICES</Text>
        <Text style={ui.h2}>Know where you’re connected.</Text>
        {data?.items.map((d) => (
          <View
            key={d.id}
            style={{
              gap: 12,
              paddingVertical: 12,
              borderBottomWidth: 1,
              borderColor: colors.line,
            }}
          >
            <View style={ui.row}>
              <Text style={{ color: colors.text, fontWeight: "600", flex: 1 }}>
                {d.deviceName}
              </Text>
              {d.current && <Text style={ui.badge}>This device</Text>}
            </View>
            <Text style={[ui.body, { fontSize: 12 }]}>
              Signed in {date(d.createdAt)} · Expires {date(d.expiresAt)}
            </Text>
            <Button
              title={
                d.current ? "Sign out this device" : "Revoke device access"
              }
              subtle
              onPress={() => {
                setActionError("");
                setSelected(d);
              }}
            />
          </View>
        ))}
        <Text style={[ui.body, { fontSize: 11 }]}>
          Device names are labels supplied at sign-in. Revoke sessions you do
          not recognize. Sessions expire automatically.
        </Text>
        <View style={ui.row}>
          <Button
            title="Previous devices"
            subtle
            disabled={!offset || loading}
            onPress={() => setOffset(Math.max(0, offset - 25))}
          />
          <Button
            title="More devices"
            subtle
            disabled={!data?.hasMore || loading}
            onPress={() => setOffset(offset + 25)}
          />
        </View>
      </View>
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
