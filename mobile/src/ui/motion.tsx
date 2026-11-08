import { useEffect, useState } from "react";
import type { ReactNode } from "react";
import { Pressable, Text, View } from "react-native";
import Animated, {
  FadeInDown,
  useAnimatedStyle,
  useSharedValue,
  withTiming,
  Easing,
} from "react-native-reanimated";
import { select } from "./haptics";
import { colors, fonts, scheme, shadow } from "./theme";

const ease = Easing.out(Easing.cubic);

/** Gentle staggered entrance for list items. */
export function Appear({
  index = 0,
  children,
}: {
  index?: number;
  children: ReactNode;
}) {
  return (
    <Animated.View
      entering={FadeInDown.duration(320)
        .delay(Math.min(index, 6) * 45)
        .easing(ease)}
    >
      {children}
    </Animated.View>
  );
}

/** Single-choice filter with a sliding selection. Each option stays a named button. */
export function Segmented<T extends string>({
  value,
  options,
  onChange,
  disabled = false,
}: {
  value: T;
  options: { value: T; label: string }[];
  onChange: (value: T) => void;
  disabled?: boolean;
}) {
  const [width, setWidth] = useState(0);
  const index = Math.max(
    0,
    options.findIndex((o) => o.value === value),
  );
  const pad = 4,
    gap = 4;
  const segment =
    width > 0
      ? (width - pad * 2 - gap * (options.length - 1)) / options.length
      : 0;
  const offset = useSharedValue(0);
  useEffect(() => {
    offset.value = withTiming(index * (segment + gap), {
      duration: 260,
      easing: ease,
    });
  }, [index, segment, offset]);
  const indicator = useAnimatedStyle(() => ({
    transform: [{ translateX: offset.value }],
  }));
  return (
    <View
      onLayout={(e) => setWidth(e.nativeEvent.layout.width)}
      style={{
        flexDirection: "row",
        backgroundColor: colors.sunken,
        borderRadius: 999,
        padding: pad,
        gap,
        opacity: disabled ? 0.5 : 1,
      }}
    >
      {segment > 0 && (
        <Animated.View
          pointerEvents="none"
          style={[
            {
              position: "absolute",
              top: pad,
              bottom: pad,
              left: pad,
              width: segment,
              borderRadius: 999,
              backgroundColor: scheme === "dark" ? colors.dark : colors.card,
              boxShadow: shadow.card,
            },
            indicator,
          ]}
        />
      )}
      {options.map((option) => {
        const active = option.value === value;
        return (
          <Pressable
            key={option.value}
            accessibilityRole="button"
            accessibilityLabel={option.label}
            accessibilityState={{ selected: active, disabled }}
            disabled={disabled}
            onPress={() => {
              if (!active) select();
              onChange(option.value);
            }}
            style={{
              flex: 1,
              minHeight: 40,
              borderRadius: 999,
              alignItems: "center",
              justifyContent: "center",
              paddingHorizontal: 10,
            }}
          >
            <Text
              numberOfLines={1}
              style={{
                fontSize: 13,
                fontWeight: "600",
                color: active ? colors.ink : colors.muted,
                fontFamily: fonts.sans,
              }}
            >
              {option.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}
