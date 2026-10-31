import { useCallback, useEffect, useState } from "react";
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
  eventTime,
} from "@/core/events";
import { label } from "@/core/money";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen, Sheet } from "@/ui/components";
import { ui } from "@/ui/theme";
export default function EventDetail() {
  const { id } = useLocalSearchParams<{ id: string }>(),
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
    <Screen refreshing={loading} onRefresh={() => void refresh()}>
      <Button title="Back to events" subtle onPress={() => router.back()} />
      <Message text={error} error />
      {!confirm && <Message text={actionError} error />}
      {data && (
        <>
          <View style={{ gap: 12 }}>
            <Text style={ui.eyebrow}>{eventTime(data)}</Text>
            <Text style={ui.title}>{data.title}</Text>
            <Text style={ui.body}>
              {data.timeZone} · {data.venueName}
            </Text>
          </View>
          <View style={ui.card}>
            <Text style={ui.h2}>A place to come together.</Text>
            <Text style={ui.body}>
              {data.description || "We look forward to gathering with you."}
            </Text>
            <Text style={ui.body}>{data.venueAddress}</Text>
            <Text style={ui.body}>
              Ends{" "}
              {new Date(data.endsAt).toLocaleTimeString("en-ZA", {
                timeZone: data.timeZone,
                hour: "2-digit",
                minute: "2-digit",
              })}{" "}
              · {data.timeZone}
            </Text>
            {data.status === "CANCELLED" && (
              <Message text="This gathering has been cancelled." />
            )}
            {data.booking && (
              <Text style={ui.badge}>
                {data.booking.attendance === "PRESENT"
                  ? "Checked in"
                  : `Your booking · ${label(data.booking.status)}`}
              </Text>
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
                  subtle
                  disabled={busy}
                  onPress={() => setConfirm("cancel")}
                />
              )}
          </View>
          {data.booking?.status === "CONFIRMED" &&
            data.booking.attendance !== "PRESENT" &&
            data.status === "SCHEDULED" && (
              <View style={ui.card}>
                <Text style={ui.h2}>A warm welcome starts here.</Text>
                <Text style={ui.body}>
                  Show your attendance QR to the welcome team. Available from
                  two hours before the event until it ends. This code is for
                  your event arrival only.
                </Text>
                <Message text={attendanceAvailability(data, now)} />
                {pass && seconds > 0 ? (
                  <>
                    <Image
                      accessibilityLabel="Event attendance QR code"
                      source={{ uri: pass.image }}
                      cachePolicy="none"
                      style={{ width: 246, height: 246, alignSelf: "center" }}
                      contentFit="contain"
                    />
                    <Text style={[ui.body, { textAlign: "center" }]}>
                      Valid for {Math.floor(seconds / 60)}:
                      {String(seconds % 60).padStart(2, "0")}. Keep this code
                      private.
                    </Text>
                  </>
                ) : (
                  pass && (
                    <Message text="This code has expired. Generate a new code when you reach the welcome team." />
                  )
                )}
                <Button
                  title={
                    pass ? "Refresh attendance code" : "Show attendance code"
                  }
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
