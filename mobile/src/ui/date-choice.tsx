import { useState } from "react";
import { Pressable, Text, View } from "react-native";
import { Button, Sheet } from "./components";
import { colors, ui } from "./theme";
import { localDate } from "@/core/volunteering";
export function DateChoice({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
}) {
  const [open, setOpen] = useState(false),
    [month, setMonth] = useState(value.slice(0, 7));
  const [year, number] = month.split("-").map(Number);
  const first = new Date(year, number - 1, 1, 12),
    offset = (first.getDay() + 6) % 7;
  const count = new Date(year, number, 0, 12).getDate();
  const move = (amount: number) =>
    setMonth(localDate(new Date(year, number - 1 + amount, 1, 12)).slice(0, 7));
  return (
    <View>
      <Text style={ui.label}>{label}</Text>
      <Button
        title={`${label}: ${value}`}
        subtle
        onPress={() => {
          setMonth(value.slice(0, 7));
          setOpen(true);
        }}
      />
      <Sheet title={label} visible={open} onClose={() => setOpen(false)}>
        <View style={ui.row}>
          <Button title="Previous month" subtle onPress={() => move(-1)} />
          <Text style={[ui.body, { flex: 1, textAlign: "center" }]}>
            {first.toLocaleDateString("en-ZA", {
              month: "long",
              year: "numeric",
            })}
          </Text>
          <Button title="Next month" subtle onPress={() => move(1)} />
        </View>
        <View style={{ flexDirection: "row", flexWrap: "wrap" }}>
          {["M", "T", "W", "T", "F", "S", "S"].map((day, i) => (
            <Text
              key={`day-${i}`}
              style={{
                width: "14.28%",
                textAlign: "center",
                paddingVertical: 12,
                color: colors.muted,
              }}
            >
              {day}
            </Text>
          ))}
          {Array.from({ length: offset }, (_, i) => (
            <View key={`blank-${i}`} style={{ width: "14.28%" }} />
          ))}
          {Array.from({ length: count }, (_, i) => {
            const date = `${month}-${String(i + 1).padStart(2, "0")}`;
            return (
              <Pressable
                key={date}
                accessibilityRole="button"
                accessibilityLabel={date}
                accessibilityState={{ selected: date === value }}
                onPress={() => {
                  onChange(date);
                  setOpen(false);
                }}
                style={{
                  width: "14.28%",
                  minHeight: 44,
                  alignItems: "center",
                  justifyContent: "center",
                  borderRadius: 12,
                  backgroundColor:
                    date === value ? colors.green : "transparent",
                }}
              >
                <Text
                  style={{
                    color: date === value ? colors.onPrimary : colors.text,
                  }}
                >
                  {i + 1}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </Sheet>
    </View>
  );
}
export const timeOptions = Array.from({ length: 48 }, (_, i) => {
  const value = `${String(Math.floor(i / 2)).padStart(2, "0")}:${i % 2 ? "30" : "00"}`;
  return { value, label: value };
});
