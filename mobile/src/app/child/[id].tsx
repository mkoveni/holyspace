import { useCallback, useEffect, useRef, useState } from "react";
import { AppState, Text, View } from "react-native";
import { Image } from "expo-image";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import type {
  FamilyChild,
  FamilyLabel,
  FamilyPage,
  FamilyProgramme,
  FamilySession,
  FamilyVisit,
} from "@/core/models";
import {
  arrivalReference,
  collectionReference,
  familyArrivalOpen,
  familyArrivalReason,
  familyQr,
  familyTime,
  readinessLabels,
} from "@/core/family";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen, Sheet } from "@/ui/components";
import { colors, ui } from "@/ui/theme";
export default function ChildDetail() {
  const { id } = useLocalSearchParams<{ id: string }>(),
    { api } = useSession();
  const root = `/api/member/children/${encodeURIComponent(id)}`;
  const [tab, setTab] = useState("programmes"),
    [programmeOffset, setProgrammeOffset] = useState(0),
    [sessionOffset, setSessionOffset] = useState(0),
    [historyOffset, setHistoryOffset] = useState(0);
  const child = useLoad(() => api<FamilyChild>(root), [id]);
  const programmes = useLoad(
    (key) =>
      api<FamilyPage<FamilyProgramme>>(
        `${root}/programmes?limit=5&offset=${JSON.parse(key)[1]}`,
      ),
    [id, programmeOffset],
  );
  const sessions = useLoad(
    (key) =>
      api<FamilyPage<FamilySession>>(
        `${root}/sessions?limit=5&offset=${JSON.parse(key)[1]}`,
      ),
    [id, sessionOffset],
  );
  const history = useLoad(
    (key) =>
      api<FamilyPage<FamilyVisit>>(
        `${root}/attendance?limit=5&offset=${JSON.parse(key)[1]}`,
      ),
    [id, historyOffset],
  );
  const [review, setReview] = useState<FamilyVisit | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [now, setNow] = useState(() => Date.now());
  const [pass, setPass] = useState<{
    image: string;
    kind: "arrival" | "collection";
    expiresAt: number;
    label?: FamilyLabel;
    sessionId?: string;
  } | null>(null);
  const epoch = useRef(0),
    focused = useRef(false),
    foreground = useRef(true);
  const clear = useCallback(() => {
    epoch.current++;
    setPass(null);
  }, []);
  useFocusEffect(
    useCallback(() => {
      focused.current = true;
      return () => {
        focused.current = false;
        clear();
      };
    }, [clear]),
  );
  useEffect(() => {
    const listener = AppState.addEventListener("change", (state) => {
      foreground.current = state === "active";
      if (state !== "active") clear();
    });
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => {
      listener.remove();
      clearInterval(timer);
    };
  }, [clear]);
  async function refresh() {
    clear();
    setReview(null);
    await Promise.all([
      child.reload(),
      programmes.reload(),
      sessions.reload(),
      history.reload(),
    ]);
  }
  async function issue() {
    if (!review || busy) return;
    setBusy(true);
    setError("");
    clear();
    const run = epoch.current;
    try {
      const label = await api<FamilyLabel>(
        `${root}/attendance/${review.visit.id}/pickup-label`,
        "POST",
        { expectedVersion: review.visit.version, replacePrevious: true },
      );
      setReview(null);
      await history.reload();
      if (run === epoch.current && focused.current && foreground.current)
        setPass({
          image: familyQr(collectionReference(label)),
          kind: "collection",
          expiresAt: Date.parse(label.expiresAt),
          label,
        });
    } catch (e) {
      setReview(null);
      setError(
        e instanceof Error
          ? e.message
          : "Unable to issue the label. Refresh before trying again.",
      );
      await history.reload();
    } finally {
      setBusy(false);
    }
  }
  const authorized =
    Boolean(child.data) &&
    !child.error &&
    !programmes.error &&
    !sessions.error &&
    !history.error;
  const validPass =
    pass &&
    authorized &&
    pass.expiresAt > now &&
    (pass.kind === "arrival"
      ? sessions.data?.items.some(
          (s) => s.id === pass.sessionId && familyArrivalOpen(s, now),
        )
      : history.data?.items.some(
          (v) =>
            v.visit.id === pass.label?.attendanceId &&
            v.visit.version === pass.label?.attendanceVersion &&
            v.visit.collectedAt === null &&
            v.canRequestPickupLabel,
        ));
  const page =
    tab === "programmes" ? programmes : tab === "sessions" ? sessions : history;
  const offset =
    tab === "programmes"
      ? programmeOffset
      : tab === "sessions"
        ? sessionOffset
        : historyOffset;
  const setOffset =
    tab === "programmes"
      ? setProgrammeOffset
      : tab === "sessions"
        ? setSessionOffset
        : setHistoryOffset;
  return (
    <Screen
      refreshing={child.loading || page.loading}
      onRefresh={() => void refresh()}
    >
      <Button
        title="Back to my children"
        subtle
        onPress={() => router.navigate("/children")}
      />
      <Text style={ui.eyebrow}>FAMILY MINISTRY</Text>
      <Text style={ui.title}>{child.data?.name || "Your child"}</Text>
      <Message
        text={
          child.error ||
          programmes.error ||
          sessions.error ||
          history.error ||
          error
        }
        error
      />
      {authorized && (
        <>
          <View style={{ flexDirection: "row", gap: 8 }}>
            {["programmes", "sessions", "attendance"].map((value) => (
              <View key={value} style={{ flex: 1 }}>
                <Button
                  title={value[0].toUpperCase() + value.slice(1)}
                  subtle={tab !== value}
                  onPress={() => {
                    clear();
                    setTab(value);
                  }}
                />
              </View>
            ))}
          </View>
          {tab === "programmes" && (
            <>
              <Text style={ui.body}>
                Preparation today. Future enrolments may have outstanding steps
                until their start date. Readiness does not reserve a place in a
                session.
              </Text>
              {programmes.data?.items.map((p) => (
                <View key={`${p.id}-${p.validFrom}`} style={ui.card}>
                  <Text style={ui.h2}>{p.name}</Text>
                  <Text style={ui.body}>
                    {p.className || "Class placement needs review"}
                  </Text>
                  <Text style={ui.body}>
                    {p.validFrom} until {p.validUntil} (exclusive)
                  </Text>
                  <Text style={ui.eyebrow}>READINESS TODAY · {p.timeZone}</Text>
                  {p.readiness.map((step) => (
                    <Text
                      key={step.code}
                      style={[
                        ui.body,
                        {
                          color: step.complete ? colors.green : colors.warning,
                        },
                      ]}
                    >
                      {step.complete ? "✓" : "○"}{" "}
                      {readinessLabels[step.code] || step.code}
                    </Text>
                  ))}
                </View>
              ))}
            </>
          )}
          {tab === "sessions" && (
            <>
              <Text style={ui.body}>
                Your child’s next 90 days, based on dated class placement. Staff
                record each arrival and check current safe capacity.
              </Text>
              {sessions.data?.items.map((s) => (
                <View key={s.id} style={ui.card}>
                  <Text style={ui.eyebrow}>
                    {s.status.replaceAll("_", " ")}
                  </Text>
                  <Text style={ui.h2}>
                    {s.programme} · {s.className}
                  </Text>
                  <Text style={ui.body}>
                    {familyTime(s.startsAt, s.timeZone)} –{" "}
                    {familyTime(s.endsAt, s.timeZone)}
                  </Text>
                  <Text style={ui.body}>
                    {s.location} · {s.room} · {s.timeZone}
                  </Text>
                  <Message text={familyArrivalReason(s, now)} />
                  {s.issues.map((issue, i) => (
                    <Text key={i} style={ui.body}>
                      • {issue}
                    </Text>
                  ))}
                  <Button
                    title="Show check-in reference"
                    disabled={!familyArrivalOpen(s, now)}
                    onPress={() => {
                      clear();
                      setPass({
                        image: familyQr(arrivalReference(id, s.id)),
                        kind: "arrival",
                        expiresAt: Math.min(
                          Date.now() + 300000,
                          Date.parse(s.endsAt),
                        ),
                        sessionId: s.id,
                      });
                    }}
                  />
                </View>
              ))}
            </>
          )}
          {tab === "attendance" && (
            <>
              <Message text="History starts with your current verified guardian authority. Collection permission is checked separately; missing pickup rights should be reviewed with your coordinator." />
              {history.data?.items.map((item) => (
                <View key={item.visit.id} style={ui.card}>
                  <Text style={ui.eyebrow}>
                    {item.visit.collectedAt
                      ? "COLLECTED"
                      : "WITH THE MINISTRY TEAM"}
                  </Text>
                  <Text style={ui.h2}>
                    {item.visit.programme} · {item.visit.className}
                  </Text>
                  <Text style={ui.body}>
                    {item.visit.location} · {item.visit.room}
                  </Text>
                  <Text style={ui.body}>
                    Arrived{" "}
                    {familyTime(item.visit.arrivedAt, item.visit.timeZone)}
                  </Text>
                  {item.visit.collectedAt ? (
                    <Text style={ui.body}>
                      Collected{" "}
                      {familyTime(item.visit.collectedAt, item.visit.timeZone)}
                    </Text>
                  ) : (
                    <>
                      <Button
                        title="Create collection QR"
                        disabled={!item.canRequestPickupLabel || busy}
                        onPress={() => {
                          clear();
                          setReview(item);
                        }}
                      />
                      {!item.canRequestPickupLabel && (
                        <Text style={ui.body}>
                          Your own current pickup permission is required. Ask
                          the coordinator to review it.
                        </Text>
                      )}
                    </>
                  )}
                </View>
              ))}
            </>
          )}
          {!page.loading && page.data && !page.data.items.length && (
            <Message
              text={`No ${tab} on this page. Contact the ministry team if something is missing.`}
            />
          )}
          <View style={ui.row}>
            <Button
              title="Previous records"
              subtle
              disabled={offset === 0 || page.loading}
              onPress={() => {
                clear();
                setOffset(Math.max(0, offset - 5));
              }}
            />
            <Text
              numberOfLines={1}
              style={[ui.body, { fontSize: 12, flexShrink: 0 }]}
            >
              Page {offset / 5 + 1}
            </Text>
            <Button
              title="More records"
              subtle
              disabled={!page.data?.hasMore || page.loading}
              onPress={() => {
                clear();
                setOffset(offset + 5);
              }}
            />
          </View>
        </>
      )}
      {review && authorized && (
        <Sheet
          title="Replace collection label?"
          visible
          onClose={() => {
            if (!busy) setReview(null);
          }}
        >
          <Message text="Creating this QR invalidates every previous pickup label, including printed copies. The new code expires within five minutes and is shown only here. Staff still verify your identity and current pickup authorization." />
          <Button
            title="Replace label and show QR"
            busy={busy}
            onPress={() => void issue()}
          />
          <Button
            title="Keep existing label"
            subtle
            disabled={busy}
            onPress={() => setReview(null)}
          />
        </Sheet>
      )}
      {pass && (
        <Sheet
          title={
            pass.kind === "arrival" ? "Check-in reference" : "Collection QR"
          }
          visible
          onClose={clear}
        >
          {validPass ? (
            <>
              <Image
                source={{ uri: pass.image }}
                style={{ width: 260, height: 260, alignSelf: "center" }}
                contentFit="contain"
                cachePolicy="none"
                accessibilityLabel={
                  pass.kind === "arrival"
                    ? "Child check-in reference QR"
                    : "Child collection QR"
                }
              />
              <Text style={ui.body}>
                {pass.kind === "arrival"
                  ? "This reference helps staff find the correct child and session. It does not prove identity, reserve capacity or record an arrival."
                  : "Show this code to the ministry team. Staff must verify the collecting adult before handover."}
              </Text>
              {pass.label && (
                <Text
                  selectable
                  style={{ textAlign: "center", color: colors.text }}
                >
                  {pass.label.pickupCode}
                </Text>
              )}
              <Text style={ui.body}>
                Expires in{" "}
                {Math.max(0, Math.ceil((pass.expiresAt - now) / 1000))} seconds.
                Hidden when you leave this screen or app.
              </Text>
            </>
          ) : (
            <Message text="This code is no longer displayed. Close and refresh to review current details before creating another." />
          )}
          <Button title="Hide QR" subtle onPress={clear} />
        </Sheet>
      )}
    </Screen>
  );
}
