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
import { success } from "@/ui/haptics";
import {
  Badge,
  Button,
  EmptyState,
  IconTile,
  Message,
  PageHeader,
  Pager,
  Screen,
  SectionHeader,
  Sheet,
} from "@/ui/components";
import { ui } from "@/ui/theme";
import { SkeletonList } from "@/ui/skeleton";
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
      success();
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
      back={{ label: "Back to home", onPress: () => router.navigate("/") }}
    >
      <PageHeader
        eyebrow="Your time, your impact"
        title="My volunteering"
        intro="Your next 90 days of serving, with everything you need to feel prepared."
      />
      <Message text={error} error />
      <Message text={notice} />
      {!data && !error && <SkeletonList count={2} />}
      {data && !data.permitted && (
        <Message text="Ask your church office to enable volunteer self-service access for your account." />
      )}
      {data?.permitted && !data.linked && (
        <Message text="Your volunteer profile is not connected to this account. Ask your coordinator to verify and link it." />
      )}
      {data?.linked && (
        <>
          <View style={[ui.card, { gap: 12 }]}>
            <View
              style={{ flexDirection: "row", gap: 12, alignItems: "center" }}
            >
              <IconTile icon="calendar-clear-outline" tone="gold" />
              <Text style={[ui.h2, { flex: 1 }]}>Make space to serve</Text>
              <Badge
                text={(data.status ?? "")
                  .toLowerCase()
                  .replace(/^./, (c) => c.toUpperCase())}
                tone={data.status === "ACTIVE" ? "success" : "warning"}
              />
            </View>
            <Text style={ui.body}>
              Profile: {data.status?.toLowerCase()}. Keep your weekly
              availability and time away up to date.
            </Text>
            <Button
              title="Manage availability"
              variant="secondary"
              icon="create-outline"
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
          <SectionHeader title="Upcoming duties" />
          {!data.items.length && (
            <EmptyState
              icon="hand-left-outline"
              text="No duties on this page. Your coordinator will add assignments and send invitations when a response is needed."
            />
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
          <Pager
            hasPrevious={offset > 0}
            hasNext={data.hasMore}
            disabled={loading}
            page={`Page ${offset / 10 + 1}`}
            previous={{
              title: "Previous duties",
              onPress: () => setOffset(Math.max(0, offset - 10)),
            }}
            next={{
              title: "More duties",
              onPress: () => setOffset(offset + 10),
            }}
          />
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
            <View
              style={{ flexDirection: "row", gap: 12, alignItems: "center" }}
            >
              <IconTile icon="hand-left-outline" tone="gold" />
              <Text style={[ui.h2, { flex: 1 }]}>
                {selected.team} · {selected.role}
              </Text>
            </View>
            <Message text="Accepting confirms your commitment. Outstanding readiness requirements still need your coordinator’s attention. Declining releases your place." />
            <Button
              title="Accept duty"
              variant="gold"
              icon="checkmark"
              busy={busy}
              disabled={!canRespond(selected, now)}
              onPress={() => void respond("ACCEPTED")}
            />
            <Button
              title="Decline duty"
              variant="ghost"
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
