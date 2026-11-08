import { useCallback, useEffect, useRef, useState } from "react";
import { AppState, Text, View } from "react-native";
import { Image } from "expo-image";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { randomUUID } from "expo-crypto";
import type { EventPass, MemberEvent } from "@/core/models";
import { useSession } from "@/core/session";
import { ApiError } from "@/core/http";
import {
  admissionOpen,
  attendanceQr,
  attendanceAvailability,
  canRegister,
  eventDay,
  eventTime,
} from "@/core/events";
import { label } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import {
  Badge,
  Button,
  InfoRow,
  Message,
  Screen,
  Sheet,
} from "@/ui/components";
import { CoverBanner } from "@/ui/cover-image";
import { DateTile } from "@/ui/event-card";
import { success } from "@/ui/haptics";
import { colors, ui } from "@/ui/theme";
import { SkeletonBlock } from "@/ui/skeleton";
export default function EventDetail() {
  const { id, pass: openPass } = useLocalSearchParams<{
      id: string;
      pass?: string;
    }>(),
    { api } = useSession();
  const { data, loading, error, reload } = useLoad(
    () => api<MemberEvent>(`/api/member/events/${encodeURIComponent(id)}`),
    [id],
  );
  const [busy, setBusy] = useState(false),
    [actionError, setActionError] = useState("");
  const [confirm, setConfirm] = useState<"register" | "cancel" | null>(null);
  const [intent, setIntent] = useState<{
    requestId: string;
    expectedVersion?: number;
  } | null>(null);
  const [pass, setPass] = useState<(EventPass & { image: string }) | null>(
      null,
    ),
    [now, setNow] = useState(() => Date.now());
  useFocusEffect(
    useCallback(
      () => () => {
        setPass(null);
      },
      [],
    ),
  );
  useEffect(() => {
    const listener = AppState.addEventListener("change", (state) => {
      if (state !== "active") setPass(null);
    });
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => {
      listener.remove();
      clearInterval(timer);
    };
  }, []);
  // Arriving from the Home "Show attendance QR" shortcut issues one code, once.
  const autoPass = useRef(openPass === "1");
  useEffect(() => {
    if (!autoPass.current || !data || !admissionOpen(data, now)) return;
    autoPass.current = false;
    queueMicrotask(() => void showPass());
  });
  async function refresh() {
    setPass(null);
    await reload();
  }
  async function act() {
    if (!data || busy || !confirm) return;
    setBusy(true);
    setActionError("");
    setPass(null);
    const action = confirm;
    try {
      if (action === "register") {
        const request = intent || {
          requestId: randomUUID(),
          ...(data.booking ? { expectedVersion: data.booking.version } : {}),
        };
        setIntent(request);
        await api(`/api/member/events/${id}/registration`, "POST", request);
        setIntent(null);
      } else {
        await api(`/api/member/events/${id}/cancel`, "POST", {
          expectedVersion: data.booking!.version,
        });
      }
      success();
      setConfirm(null);
      await reload();
    } catch (e) {
      if (e instanceof ApiError && e.status < 500) setIntent(null);
      setActionError(
        (e instanceof Error ? e.message : "Unable to update your booking.") +
          " Refresh your booking before making another change.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function showPass() {
    if (busy) return;
    setBusy(true);
    setActionError("");
    setPass(null);
    try {
      const value = await api<EventPass>(
        `/api/member/events/${id}/attendance-pass`,
        "POST",
      );
      setPass({ ...value, image: attendanceQr(value.credential) });
    } catch (e) {
      setActionError(
        e instanceof Error
          ? e.message
          : "Unable to prepare your attendance code.",
      );
    } finally {
      setBusy(false);
    }
  }
  const seconds = pass
    ? Math.max(0, Math.ceil((new Date(pass.expiresAt).getTime() - now) / 1000))
    : 0;
  return (
    <Screen
      refreshing={loading}
      onRefresh={() => void refresh()}
      back={{ label: "Back to events", onPress: () => router.back() }}
    >
      <Message text={error} error />
      {!confirm && <Message text={actionError} error />}
      {!data && !error && <SkeletonBlock />}
      {data && (
        <>
          <CoverBanner
            url={data.coverImageUrl}
            label={`Cover image for ${data.title}`}
          />
          <View style={{ flexDirection: "row", gap: 16, alignItems: "center" }}>
            <DateTile startsAt={data.startsAt} timeZone={data.timeZone} />
            <View style={{ flex: 1, gap: 6 }}>
              <Text style={ui.eyebrow}>{eventTime(data)}</Text>
              <Text
                accessibilityRole="header"
                style={[ui.title, { fontSize: 28, lineHeight: 34 }]}
              >
                {data.title}
              </Text>
            </View>
          </View>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
            {data.status === "CANCELLED" && (
              <Badge text="Gathering cancelled" tone="danger" />
            )}
            {data.booking && (
              <Badge
                tone={
                  data.booking.attendance === "PRESENT" ||
                  data.booking.status === "CONFIRMED"
                    ? "success"
                    : data.booking.status === "CANCELLED"
                      ? "danger"
                      : "gold"
                }
                text={
                  data.booking.attendance === "PRESENT"
                    ? "Checked in"
                    : `Your booking · ${label(data.booking.status)}`
                }
              />
            )}
          </View>
          <View style={ui.group}>
            <InfoRow
              icon="time-outline"
              title={`${eventDay(data).time} – ${new Date(
                data.endsAt,
              ).toLocaleTimeString("en-ZA", {
                timeZone: data.timeZone,
                hour: "2-digit",
                minute: "2-digit",
              })}`}
              subtitle={data.timeZone}
            />
            <InfoRow
              last
              icon="location-outline"
              title={data.venueName}
              subtitle={data.venueAddress}
            />
          </View>
          <View style={ui.card}>
            <Text style={ui.h2}>A place to come together.</Text>
            <Text style={ui.body}>
              {data.description || "We look forward to gathering with you."}
            </Text>
            {data.status === "CANCELLED" && (
              <Message text="This gathering has been cancelled." />
            )}
            {data.booking?.status === "WAITLISTED" && (
              <Message text="You’re on the waitlist. Your place is not confirmed yet. Refresh to check for an available place." />
            )}
            {canRegister(data, now) ? (
              <Button
                title={
                  intent
                    ? "Retry same booking"
                    : data.remainingPlaces === 0
                      ? "Join waitlist"
                      : "Reserve my place"
                }
                variant="gold"
                icon="ticket-outline"
                busy={busy}
                onPress={() => setConfirm("register")}
              />
            ) : (
              !data.booking && (
                <Message
                  text={
                    data.remainingPlaces === 0 && !data.waitlistEnabled
                      ? "This gathering is fully booked."
                      : "Registration is currently closed."
                  }
                />
              )
            )}
            {data.booking &&
              data.booking.status !== "CANCELLED" &&
              data.booking.attendance !== "PRESENT" &&
              new Date(data.endsAt).getTime() > now && (
                <Button
                  title="Cancel my booking"
                  variant="ghost"
                  disabled={busy}
                  onPress={() => setConfirm("cancel")}
                />
              )}
          </View>
          {data.booking?.status === "CONFIRMED" &&
            data.booking.attendance !== "PRESENT" &&
            data.status === "SCHEDULED" && (
              <View
                style={{
                  backgroundColor: colors.hero,
                  borderRadius: 26,
                  padding: 20,
                  gap: 14,
                }}
              >
                <Text style={[ui.eyebrow, { color: colors.goldBright }]}>
                  YOUR ATTENDANCE PASS
                </Text>
                <Text style={[ui.h2, { color: colors.onDark }]}>
                  A warm welcome starts here.
                </Text>
                <Text style={[ui.body, { color: colors.onDarkMuted }]}>
                  Show your attendance QR to the welcome team. Available from
                  two hours before the event until it ends. This code is for
                  your event arrival only.
                </Text>
                <Message text={attendanceAvailability(data, now)} />
                {pass && seconds > 0 ? (
                  <View
                    style={{
                      backgroundColor: "#FFFFFF",
                      borderRadius: 22,
                      padding: 16,
                      alignItems: "center",
                      gap: 10,
                    }}
                  >
                    <Image
                      accessibilityLabel="Event attendance QR code"
                      source={{ uri: pass.image }}
                      cachePolicy="none"
                      style={{ width: 230, height: 230 }}
                      contentFit="contain"
                    />
                    <Text style={[ui.caption, { textAlign: "center" }]}>
                      Valid for {Math.floor(seconds / 60)}:
                      {String(seconds % 60).padStart(2, "0")}. Keep this code
                      private.
                    </Text>
                  </View>
                ) : (
                  pass && (
                    <Message text="This code has expired. Generate a new code when you reach the welcome team." />
                  )
                )}
                <Button
                  title={
                    pass ? "Refresh attendance code" : "Show attendance code"
                  }
                  variant="gold"
                  icon="qr-code-outline"
                  onPress={() => void showPass()}
                  disabled={!admissionOpen(data, now)}
                  busy={busy}
                />
              </View>
            )}
        </>
      )}
      <Sheet
        title={
          confirm === "cancel" ? "Release your place?" : "Review your booking"
        }
        visible={confirm !== null}
        onClose={() => {
          if (!busy) setConfirm(null);
        }}
      >
        <Message text={actionError} error />
        <Text style={ui.body}>
          {confirm === "cancel"
            ? "Your place will be released for someone else. You can register again while registration remains open."
            : `${data?.title} · ${data ? eventTime(data) : ""}. ${data?.remainingPlaces === 0 ? "You are requesting a waitlist place, not a confirmed booking." : "Your place is confirmed only when the church accepts your booking. If the event fills, its waitlist policy applies."}`}
        </Text>
        <Button
          title={
            confirm === "cancel" ? "Confirm cancellation" : "Confirm my booking"
          }
          danger={confirm === "cancel"}
          busy={busy}
          onPress={() => void act()}
        />
      </Sheet>
    </Screen>
  );
}
