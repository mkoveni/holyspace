import { useEffect, useState } from "react";
import { Animated, View } from "react-native";
import type { DimensionValue } from "react-native";
import { colors, ui } from "./theme";

function usePulse() {
  const [value] = useState(() => new Animated.Value(0.55));
  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(value, {
          toValue: 1,
          duration: 700,
          useNativeDriver: true,
        }),
        Animated.timing(value, {
          toValue: 0.55,
          duration: 700,
          useNativeDriver: true,
        }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [value]);
  return value;
}

function Bone({
  width,
  height,
  radius = 8,
}: {
  width: DimensionValue;
  height: number;
  radius?: number;
}) {
  return (
    <View
      style={{
        width,
        height,
        borderRadius: radius,
        backgroundColor: colors.sunken,
      }}
    />
  );
}

/** Placeholder card shown while a list loads for the first time. */
function SkeletonCard() {
  return (
    <View
      style={[
        ui.card,
        { flexDirection: "row", gap: 14, alignItems: "center", padding: 14 },
      ]}
    >
      <Bone width={56} height={64} radius={16} />
      <View style={{ flex: 1, gap: 8 }}>
        <Bone width="70%" height={16} />
        <Bone width="45%" height={12} />
        <Bone width={90} height={20} radius={999} />
      </View>
    </View>
  );
}

export function SkeletonList({ count = 3 }: { count?: number }) {
  const opacity = usePulse();
  return (
    <Animated.View
      accessibilityLabel="Loading"
      accessibilityRole="progressbar"
      style={{ gap: 20, opacity }}
    >
      {Array.from({ length: count }, (_, i) => (
        <SkeletonCard key={i} />
      ))}
    </Animated.View>
  );
}

/** Placeholder for a single detail block (title and a few lines). */
export function SkeletonBlock() {
  const opacity = usePulse();
  return (
    <Animated.View
      accessibilityLabel="Loading"
      accessibilityRole="progressbar"
      style={{ gap: 20, opacity }}
    >
      <View style={{ gap: 10 }}>
        <Bone width={120} height={12} />
        <Bone width="80%" height={30} />
      </View>
      <View style={[ui.card, { gap: 12 }]}>
        <Bone width="60%" height={18} />
        <Bone width="100%" height={12} />
        <Bone width="90%" height={12} />
        <Bone width="40%" height={12} />
      </View>
    </Animated.View>
  );
}
