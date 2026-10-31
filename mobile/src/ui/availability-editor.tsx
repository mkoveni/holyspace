import { useState } from "react";
import { Text, View } from "react-native";
import type {
  MyVolunteering,
  WeeklyAvailability,
  AvailabilityException,
} from "@/core/models";
import {
  dutyTime,
  localDate,
  weekDays,
  zonedInstant,
} from "@/core/volunteering";
import { Button, Choice, Field, Message, Sheet } from "./components";
import { DateChoice, timeOptions } from "./date-choice";
import { ui } from "./theme";
export function AvailabilityEditor({
  data,
  onSave,
  onClose,
  busy,
  error,
}: {
  data: MyVolunteering;
  onSave: (
    weekly: WeeklyAvailability[],
    exceptions: AvailabilityException[],
  ) => void;
  onClose: () => void;
  busy: boolean;
  error: string;
}) {
  const [weekly, setWeekly] = useState(data.weekly),
    [exceptions, setExceptions] = useState(data.exceptions);
  const [adding, setAdding] = useState<"weekly" | "unavailable" | null>(null),
    [day, setDay] = useState("1"),
    [start, setStart] = useState("08:00"),
    [end, setEnd] = useState("17:00");
  const today = localDate(),
    nextYear = localDate(
      new Date(
        new Date().getFullYear() + 1,
        new Date().getMonth(),
        new Date().getDate(),
        12,
      ),
    );
  const [from, setFrom] = useState(today),
    [until, setUntil] = useState(nextYear),
    [zone, setZone] = useState(
      Intl.DateTimeFormat().resolvedOptions().timeZone,
    ),
    [validation, setValidation] = useState("");
  function add() {
    try {
      if (adding === "weekly") {
        new Intl.DateTimeFormat("en-ZA", { timeZone: zone }).format(new Date());
        if (until <= from || start === end)
          throw new Error(
            "Choose different times and an end date after the start date.",
          );
        setWeekly([
          ...weekly,
          {
            dayOfWeek: Number(day),
            startsAt: start,
            endsAt: end,
            timeZone: zone,
            validFrom: from,
            validUntil: until,
          },
        ]);
      } else {
        const startsAt = zonedInstant(from, start, zone),
          endsAt = zonedInstant(until, end, zone);
        if (endsAt <= startsAt)
          throw new Error("The end must be after the start.");
        setExceptions([...exceptions, { startsAt, endsAt, available: false }]);
      }
      setAdding(null);
      setValidation("");
    } catch (e) {
      setValidation(
        e instanceof Error ? e.message : "Check the dates and time zone.",
      );
    }
  }
  return (
    <Sheet
      title="My availability"
      visible
      onClose={() => {
        if (!busy) onClose();
      }}
    >
      <Message text="Saving replaces your availability. Existing duties stay assigned; tell your coordinator if you can no longer serve." />
      <Message text={error} error />
      <Text style={ui.h2}>Weekly availability</Text>
      {!weekly.length && (
        <Text style={ui.body}>No weekly availability set.</Text>
      )}
      {weekly.map((r, i) => (
        <View key={i} style={[ui.card, { gap: 8 }]}>
          <Text style={ui.label}>
            {weekDays[r.dayOfWeek - 1]} · {r.startsAt.slice(0, 5)}–
            {r.endsAt.slice(0, 5)}
            {r.endsAt <= r.startsAt ? " (next day)" : ""}
          </Text>
          <Text style={ui.body}>
            {r.timeZone} · {r.validFrom} until {r.validUntil} (exclusive)
          </Text>
          <Button
            title={`Remove weekly window ${i + 1}`}
            subtle
            disabled={busy}
            onPress={() => setWeekly(weekly.filter((_, index) => index !== i))}
          />
        </View>
      ))}
      <Button
        title="Add weekly window"
        subtle
        disabled={busy || weekly.length >= 100}
        onPress={() => {
          setAdding("weekly");
          setFrom(today);
          setUntil(nextYear);
          setValidation("");
        }}
      />
      <Text style={ui.h2}>Dated exceptions</Text>
      <Text style={ui.body}>
        Unavailable periods take priority over weekly windows. Dates below use
        your device time zone.
      </Text>
      {exceptions.map((e, i) => (
        <View key={i} style={[ui.card, { gap: 8 }]}>
          <Text style={ui.label}>
            {e.available ? "Available" : "Unavailable"}
          </Text>
          <Text style={ui.body}>
            {dutyTime(e.startsAt)} – {dutyTime(e.endsAt)}
          </Text>
          <Button
            title={`Remove exception ${i + 1}`}
            subtle
            disabled={busy}
            onPress={() =>
              setExceptions(exceptions.filter((_, index) => index !== i))
            }
          />
        </View>
      ))}
      <Button
        title="Add unavailable dates"
        subtle
        disabled={busy || exceptions.length >= 100}
        onPress={() => {
          setAdding("unavailable");
          setFrom(today);
          setUntil(today);
          setValidation("");
        }}
      />
      {adding && (
        <View style={[ui.card, { gap: 16 }]}>
          <Text style={ui.h2}>
            {adding === "weekly"
              ? "New weekly window"
              : "New unavailable period"}
          </Text>
          {adding === "weekly" && (
            <Choice
              label="Day of week"
              value={day}
              onChange={setDay}
              options={weekDays.map((label, i) => ({
                value: String(i + 1),
                label,
              }))}
            />
          )}
          <DateChoice label="From date" value={from} onChange={setFrom} />
          <Choice
            label="Start time"
            value={start}
            onChange={setStart}
            options={timeOptions}
          />
          <DateChoice
            label={adding === "weekly" ? "Until date (exclusive)" : "End date"}
            value={until}
            onChange={setUntil}
          />
          <Choice
            label="End time"
            value={end}
            onChange={setEnd}
            options={timeOptions}
          />
          <Field
            label="Time zone"
            value={zone}
            onChangeText={setZone}
            autoCapitalize="none"
            placeholder="Africa/Johannesburg"
          />
          <Message text={validation} error />
          <Button title="Add to availability" onPress={add} />
          <Button
            title="Discard new window"
            subtle
            onPress={() => setAdding(null)}
          />
        </View>
      )}
      <Button
        title="Save availability"
        busy={busy}
        disabled={adding !== null}
        onPress={() => onSave(weekly, exceptions)}
      />
      <Button title="Cancel changes" subtle disabled={busy} onPress={onClose} />
    </Sheet>
  );
}
