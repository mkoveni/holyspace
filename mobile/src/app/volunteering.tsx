import { useEffect, useState } from "react";
import { Text, View } from "react-native";
import { router } from "expo-router";
import type {
  AvailabilityException,
  MyVolunteering,
  VolunteerDuty,
  WeeklyAvailability,
} from "@/core/models";
import { useSession } from "@/core/session";
import { canRespond } from "@/core/volunteering";
import { useLoad } from "@/hooks/use-load";
import { AvailabilityEditor } from "@/ui/availability-editor";
import { DutyCard } from "@/ui/duty-card";
import { Button, Message, Screen, Sheet } from "@/ui/components";
import { ui } from "@/ui/theme";
export default function Volunteering() {
  const { api } = useSession(),
    [offset, setOffset] = useState(0);
  const { data, loading, error, reload } = useLoad(
    (key) =>
      api<MyVolunteering>(
        `/api/member/volunteering?limit=10&offset=${JSON.parse(key)[0]}`,
      ),
    [offset],
  );
  const [selected, setSelected] = useState<VolunteerDuty | null>(null),
    [editing, setEditing] = useState<MyVolunteering | null>(null),
    [busy, setBusy] = useState(false),
    [mutationError, setMutationError] = useState(""),
    [notice, setNotice] = useState(""),
    [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, []);
  async function respond(decision: "ACCEPTED" | "DECLINED") {
    if (!selected?.invitation || !canRespond(selected)) return;
    setBusy(true);
    setMutationError("");
    try {
      await api(
        `/api/member/volunteering/invitations/${selected.invitation.id}/respond`,
        "POST",
        { expectedVersion: selected.invitation.version, decision },
      );
      setNotice(
        decision === "ACCEPTED"
          ? "Invitation accepted. Readiness is checked separately."
          : "Invitation declined. Your coordinator can plan another volunteer.",
      );
    } catch (e) {
      setNotice(
        e instanceof Error
          ? e.message
          : "Unable to save your response. Refresh before trying again.",
      );
    } finally {
      setSelected(null);
      setBusy(false);
      await reload();
    }
  }
  async function save(
    weekly: WeeklyAvailability[],
    exceptions: AvailabilityException[],
  ) {
    if (editing?.version == null) return;
    setBusy(true);
    setMutationError("");
    try {
      await api("/api/member/volunteering/availability", "PUT", {
        volunteerId: editing.volunteerId,
        expectedVersion: editing.version,
        weekly,
        exceptions,
      });
      setEditing(null);
      setNotice("Availability saved. Existing duties have not changed.");
      await reload();
    } catch (e) {
      setMutationError(
        e instanceof Error
          ? e.message
          : "Unable to save availability. Close and refresh before trying again.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Screen
      refreshing={loading}
      onRefresh={() => {
        setSelected(null);
        setEditing(null);
        void reload();
      }}
    >
      <Button
        title="Back to home"
        subtle
        onPress={() => router.navigate("/")}
      />
      <Text style={ui.eyebrow}>YOUR TIME, YOUR IMPACT</Text>
      <Text style={ui.title}>My volunteering</Text>
      <Text style={ui.body}>
        Your next 90 days of serving, with everything you need to feel prepared.
      </Text>
      <Message text={error} error />
      <Message text={notice} />
      {data && !data.permitted && (
        <Message text="Ask your church office to enable volunteer self-service access for your account." />
      )}
      {data?.permitted && !data.linked && (
        <Message text="Your volunteer profile is not connected to this account. Ask your coordinator to verify and link it." />
      )}
      {data?.linked && (
        <>
          <View style={[ui.card, { gap: 12 }]}>
            <Text style={ui.h2}>Make space to serve</Text>
            <Text style={ui.body}>
              Profile: {data.status?.toLowerCase()}. Keep your weekly
              availability and time away up to date.
            </Text>
            <Button
              title="Manage availability"
              disabled={data.status !== "ACTIVE"}
              onPress={() => {
                setMutationError("");
                setEditing(data);
              }}
            />
            {data.status !== "ACTIVE" && (
              <Message text="Your volunteer profile is paused or archived. Contact your coordinator to resume serving." />
            )}
          </View>
          <Text style={ui.eyebrow}>UPCOMING DUTIES</Text>
          {!data.items.length && (
            <Message text="No duties on this page. Your coordinator will add assignments and send invitations when a response is needed." />
          )}
          {data.items.map((duty) => (
            <DutyCard
              key={duty.id}
              duty={duty}
              now={now}
              onRespond={
                data.status === "ACTIVE"
                  ? () => {
                      setMutationError("");
                      setSelected(duty);
                    }
                  : undefined
              }
            />
          ))}
          <View style={ui.row}>
            <Button
              title="Previous duties"
              subtle
              disabled={offset === 0 || loading}
              onPress={() => setOffset(Math.max(0, offset - 10))}
            />
            <Text style={ui.body}>Page {offset / 10 + 1}</Text>
            <Button
              title="More duties"
              subtle
              disabled={!data.hasMore || loading}
              onPress={() => setOffset(offset + 10)}
            />
          </View>
        </>
      )}
      {selected &&
        data?.permitted &&
        data.linked &&
        data.status === "ACTIVE" && (
          <Sheet
            title="Respond to invitation"
            visible
            onClose={() => {
              if (!busy) setSelected(null);
            }}
          >
            <Text style={ui.h2}>
              {selected.team} · {selected.role}
            </Text>
            <Message text="Accepting confirms your commitment. Outstanding readiness requirements still need your coordinator’s attention. Declining releases your place." />
            <Button
              title="Accept duty"
              busy={busy}
              disabled={!canRespond(selected, now)}
              onPress={() => void respond("ACCEPTED")}
            />
            <Button
              title="Decline duty"
              danger
              disabled={busy || !canRespond(selected, now)}
              onPress={() => void respond("DECLINED")}
            />
            {!canRespond(selected, now) && (
              <Message text="This invitation has expired. Refresh your duties to see the latest state." />
            )}
          </Sheet>
        )}
      {editing &&
        data?.permitted &&
        data.linked &&
        data.status === "ACTIVE" &&
        data.volunteerId === editing.volunteerId && (
          <AvailabilityEditor
            data={editing}
            busy={busy}
            error={mutationError}
            onSave={(w, e) => void save(w, e)}
            onClose={() => setEditing(null)}
          />
        )}
    </Screen>
  );
}
