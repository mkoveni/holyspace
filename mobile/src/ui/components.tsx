import { useCallback, useEffect, useRef, useState } from "react";
import type { ComponentProps, ReactNode } from "react";
import {
  ActivityIndicator,
  Image,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  Text,
  TextInput,
  View,
} from "react-native";
import type { StyleProp, ViewStyle } from "react-native";
import { KeyboardAwareScrollView } from "react-native-keyboard-controller";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useFocusEffect } from "expo-router";
import { setStatusBarStyle } from "expo-status-bar";
import { Ionicons } from "@expo/vector-icons";
import { appName } from "@/core/brand";
import { select, tap } from "./haptics";
import { colors, fonts, scheme, shadow, ui } from "./theme";
type IconName = keyof typeof Ionicons.glyphMap;

const logoMark = require("../../assets/images/logo-mark.png");

function Brand({ light }: { light: boolean }) {
  return (
    <View
      accessible
      accessibilityRole="header"
      accessibilityLabel={appName}
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 10,
        flexShrink: 1,
      }}
    >
      <View
        style={{
          width: 30,
          height: 30,
          borderRadius: 9,
          backgroundColor: colors.logoTile,
          alignItems: "center",
          justifyContent: "center",
          borderWidth: light ? 0 : 1,
          borderColor: colors.line,
        }}
      >
        <Image
          source={logoMark}
          style={{ width: 22, height: 22 }}
          resizeMode="contain"
        />
      </View>
      <Text
        numberOfLines={1}
        style={{
          flexShrink: 1,
          fontSize: 12,
          fontWeight: "700",
          letterSpacing: 1.6,
          color: light ? colors.goldBright : colors.green,
          fontFamily: fonts.sans,
        }}
      >
        {appName.toUpperCase()}
      </Text>
    </View>
  );
}

export function IconButton({
  icon,
  label,
  onPress,
  light = false,
  busy = false,
  dot = false,
}: {
  icon: IconName;
  label: string;
  onPress: () => void;
  light?: boolean;
  busy?: boolean;
  dot?: boolean;
}) {
  const tint = light ? colors.onDark : colors.green;
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ busy, disabled: busy }}
      onPress={onPress}
      disabled={busy}
      hitSlop={8}
      style={({ pressed }) => ({
        width: 40,
        height: 40,
        borderRadius: 20,
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: light ? "rgba(255,255,255,0.08)" : colors.card,
        borderWidth: 1,
        borderColor: light ? "rgba(255,255,255,0.12)" : colors.line,
        opacity: pressed ? 0.7 : 1,
      })}
    >
      {busy ? (
        <ActivityIndicator color={tint} size="small" />
      ) : (
        <Ionicons name={icon} size={19} color={tint} />
      )}
      {dot && (
        <View
          style={{
            position: "absolute",
            top: 8,
            right: 9,
            width: 8,
            height: 8,
            borderRadius: 4,
            backgroundColor: colors.goldBright,
            borderWidth: 1.5,
            borderColor: light ? colors.hero : colors.card,
          }}
        />
      )}
    </Pressable>
  );
}

/**
 * Page shell. With `hero`, the top of the page becomes a full-bleed forest
 * panel under the status bar; otherwise a light brand bar is shown.
 * `back` turns the brand bar into a detail-screen header.
 */
export function Screen({
  children,
  refreshing,
  onRefresh,
  hero,
  actions,
  back,
}: {
  children: ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
  hero?: ReactNode;
  actions?: ReactNode;
  back?: { label: string; onPress: () => void };
}) {
  const insets = useSafeAreaInsets();
  const dark = Boolean(hero);
  // Pull-to-refresh keeps its spinner until the screen reports loading finished.
  const [pulled, setPulled] = useState(false);
  const busyRef = useRef(false);
  useEffect(() => {
    busyRef.current = Boolean(refreshing);
  }, [refreshing]);
  const pull = useCallback(() => {
    if (!onRefresh) return;
    setPulled(true);
    onRefresh();
    const release = () => {
      if (busyRef.current) setTimeout(release, 250);
      else setPulled(false);
    };
    setTimeout(release, 400);
  }, [onRefresh]);
  useFocusEffect(
    useCallback(() => {
      setStatusBarStyle(dark || scheme === "dark" ? "light" : "dark");
    }, [dark]),
  );
  const bar = (
    <View style={[ui.row, { paddingHorizontal: 20, paddingVertical: 12 }]}>
      {back ? (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={back.label}
          onPress={back.onPress}
          hitSlop={10}
          style={({ pressed }) => ({
            flexDirection: "row",
            alignItems: "center",
            gap: 4,
            opacity: pressed ? 0.6 : 1,
          })}
        >
          <Ionicons name="chevron-back" size={22} color={colors.green} />
          <Text
            numberOfLines={1}
            style={{
              color: colors.green,
              fontSize: 15,
              fontWeight: "600",
              fontFamily: fonts.sans,
            }}
          >
            {back.label
              .replace(/^Back to /, "")
              .replace(/^./, (c) => c.toUpperCase())}
          </Text>
        </Pressable>
      ) : (
        <Brand light={dark} />
      )}
      <View style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
        {actions}
        {onRefresh && (
          <IconButton
            icon="refresh"
            label="Refresh"
            light={dark}
            busy={refreshing}
            onPress={onRefresh}
          />
        )}
      </View>
    </View>
  );
  const refreshControl =
    onRefresh && Platform.OS !== "web" ? (
      <RefreshControl
        refreshing={pulled}
        onRefresh={pull}
        tintColor={dark ? colors.goldBright : colors.green}
      />
    ) : undefined;
  return (
    <View style={[ui.screen, dark && { backgroundColor: colors.bg }]}>
      {!dark && <View style={{ paddingTop: insets.top }}>{bar}</View>}
      {dark && (
        // Paints the status-bar area so overscroll never reveals ivory above the hero.
        <View
          pointerEvents="none"
          style={{
            position: "absolute",
            top: 0,
            left: 0,
            right: 0,
            height: 160,
            backgroundColor: colors.hero,
          }}
        />
      )}
      <KeyboardAwareScrollView
        style={{ flex: 1 }}
        bottomOffset={24}
        keyboardDismissMode="on-drag"
        keyboardShouldPersistTaps="handled"
        refreshControl={refreshControl}
        contentContainerStyle={
          dark
            ? { paddingBottom: 40 + insets.bottom, flexGrow: 1 }
            : [ui.content, { paddingTop: 8, paddingBottom: 40 + insets.bottom }]
        }
      >
        {dark ? (
          <>
            <View
              style={{
                backgroundColor: colors.hero,
                paddingTop: insets.top,
                borderBottomLeftRadius: 32,
                borderBottomRightRadius: 32,
              }}
            >
              {bar}
              <View
                style={{
                  paddingHorizontal: 20,
                  paddingBottom: 26,
                  paddingTop: 6,
                  width: "100%",
                  maxWidth: 720,
                  alignSelf: "center",
                  gap: 18,
                }}
              >
                {hero}
              </View>
            </View>
            <View
              style={[ui.content, { backgroundColor: colors.bg, flexGrow: 1 }]}
            >
              {children}
            </View>
          </>
        ) : (
          children
        )}
      </KeyboardAwareScrollView>
    </View>
  );
}

export type ButtonVariant =
  "primary" | "secondary" | "gold" | "danger" | "ghost";
const buttonTones: Record<
  ButtonVariant,
  { bg: string; fg: string; border: string }
> = {
  primary: { bg: colors.green, fg: colors.onPrimary, border: colors.green },
  secondary: { bg: colors.card, fg: colors.green, border: colors.line },
  gold: { bg: colors.goldBright, fg: colors.onGold, border: colors.goldBright },
  danger: { bg: colors.danger, fg: colors.onDanger, border: colors.danger },
  ghost: { bg: "transparent", fg: colors.green, border: "transparent" },
};
export function Button({
  title,
  onPress,
  busy = false,
  disabled = false,
  subtle = false,
  danger = false,
  variant,
  icon,
  style,
  label,
}: {
  title: string;
  /** Accessible name when it should differ from the visible title. */
  label?: string;
  onPress: () => void;
  busy?: boolean;
  disabled?: boolean;
  /** Shorthand for variant="secondary". */
  subtle?: boolean;
  /** Shorthand for variant="danger". */
  danger?: boolean;
  variant?: ButtonVariant;
  icon?: IconName;
  style?: StyleProp<ViewStyle>;
}) {
  const tone =
    buttonTones[
      variant ?? (danger ? "danger" : subtle ? "secondary" : "primary")
    ];
  const inactive = disabled || busy;
  const strong = tone.bg !== colors.card && tone.bg !== "transparent";
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: inactive, busy }}
      accessibilityLabel={label ?? title}
      onPress={() => {
        if (strong) tap();
        onPress();
      }}
      disabled={inactive}
      style={({ pressed }) => [
        {
          minHeight: 52,
          paddingVertical: 14,
          paddingHorizontal: 22,
          borderRadius: 999,
          backgroundColor: tone.bg,
          borderWidth: 1,
          borderColor: tone.border,
          opacity: inactive ? 0.45 : 1,
          alignItems: "center",
          justifyContent: "center",
          flexDirection: "row",
          gap: 8,
          transform: [{ scale: pressed && !inactive ? 0.98 : 1 }],
        },
        style,
      ]}
    >
      {busy ? (
        <ActivityIndicator color={tone.fg} />
      ) : (
        <>
          {icon && <Ionicons name={icon} size={18} color={tone.fg} />}
          <Text
            style={{
              fontSize: 15,
              fontWeight: "600",
              color: tone.fg,
              textAlign: "center",
              fontFamily: fonts.sans,
            }}
          >
            {title}
          </Text>
        </>
      )}
    </Pressable>
  );
}

/** Quiet text action, used for "See all" style navigation. */
export function LinkButton({
  title,
  label,
  onPress,
  light = false,
}: {
  title: string;
  /** Accessible name when it should differ from the visible text. */
  label?: string;
  onPress: () => void;
  light?: boolean;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label ?? title}
      onPress={onPress}
      hitSlop={10}
      style={({ pressed }) => ({
        flexDirection: "row",
        alignItems: "center",
        gap: 2,
        opacity: pressed ? 0.6 : 1,
      })}
    >
      <Text
        style={{
          color: light ? colors.goldBright : colors.gold,
          fontSize: 13,
          fontWeight: "600",
          fontFamily: fonts.sans,
        }}
      >
        {title}
      </Text>
      <Ionicons
        name="chevron-forward"
        size={14}
        color={light ? colors.goldBright : colors.gold}
      />
    </Pressable>
  );
}

export function SectionHeader({
  title,
  action,
}: {
  title: string;
  action?: { title: string; label?: string; onPress: () => void };
}) {
  return (
    <View style={[ui.row, { marginTop: 6, marginBottom: -6 }]}>
      <Text
        accessibilityRole="header"
        style={{
          fontSize: 18,
          fontWeight: "600",
          letterSpacing: -0.3,
          color: colors.text,
          fontFamily: fonts.sans,
        }}
      >
        {title}
      </Text>
      {action && <LinkButton {...action} />}
    </View>
  );
}

export type Tone = "neutral" | "success" | "warning" | "danger" | "gold";
const badgeTones: Record<Tone, { bg: string; fg: string }> = {
  neutral: { bg: colors.pale, fg: colors.green },
  success: { bg: colors.successBg, fg: colors.success },
  warning: { bg: colors.warningBg, fg: colors.warning },
  danger: { bg: colors.dangerBg, fg: colors.danger },
  gold: { bg: colors.goldSoft, fg: colors.gold },
};
export function Badge({
  text,
  tone = "neutral",
}: {
  text: string;
  tone?: Tone;
}) {
  const t = badgeTones[tone];
  return (
    <Text
      style={[
        ui.badge,
        { backgroundColor: t.bg, color: t.fg, alignSelf: "flex-start" },
      ]}
    >
      {text}
    </Text>
  );
}

export function IconTile({
  icon,
  tone = "neutral",
  size = 44,
}: {
  icon: IconName;
  tone?: Tone;
  size?: number;
}) {
  const t = badgeTones[tone];
  return (
    <View
      style={{
        width: size,
        height: size,
        borderRadius: size * 0.32,
        backgroundColor: t.bg,
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <Ionicons name={icon} size={size * 0.46} color={t.fg} />
    </View>
  );
}

/** A tappable row inside a `ui.group` card. */
export function ListRow({
  title,
  subtitle,
  icon,
  tone,
  onPress,
  last = false,
  trailing,
}: {
  title: string;
  subtitle?: string;
  icon?: IconName;
  tone?: Tone;
  onPress: () => void;
  last?: boolean;
  trailing?: ReactNode;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={title}
      onPress={onPress}
      style={({ pressed }) => [
        ui.row,
        {
          paddingVertical: 14,
          paddingHorizontal: 16,
          backgroundColor: pressed ? colors.sunken : "transparent",
          borderBottomWidth: last ? 0 : 1,
          borderColor: colors.line,
        },
      ]}
    >
      {icon && <IconTile icon={icon} tone={tone} size={38} />}
      <View style={{ flex: 1, gap: 2 }}>
        <Text style={[ui.h3, { fontSize: 15 }]}>{title}</Text>
        {subtitle ? <Text style={ui.caption}>{subtitle}</Text> : null}
      </View>
      {trailing ?? (
        <Ionicons name="chevron-forward" size={17} color={colors.faint} />
      )}
    </Pressable>
  );
}

export function QuickAction({
  icon,
  title,
  label,
  onPress,
  dot = false,
}: {
  icon: IconName;
  title: string;
  label?: string;
  onPress: () => void;
  dot?: boolean;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label ?? title}
      onPress={onPress}
      style={({ pressed }) => ({
        flex: 1,
        alignItems: "center",
        gap: 8,
        transform: [{ scale: pressed ? 0.96 : 1 }],
      })}
    >
      <View
        style={{
          width: "100%",
          maxWidth: 76,
          aspectRatio: 1,
          borderRadius: 22,
          backgroundColor: colors.card,
          borderWidth: 1,
          borderColor: colors.line,
          alignItems: "center",
          justifyContent: "center",
          boxShadow: shadow.card,
        }}
      >
        <Ionicons name={icon} size={24} color={colors.green} />
        {dot && (
          <View
            style={{
              position: "absolute",
              top: 12,
              right: 14,
              width: 9,
              height: 9,
              borderRadius: 5,
              backgroundColor: colors.goldBright,
            }}
          />
        )}
      </View>
      <Text
        numberOfLines={1}
        style={{
          fontSize: 12,
          fontWeight: "600",
          color: colors.text,
          fontFamily: fonts.sans,
        }}
      >
        {title}
      </Text>
    </Pressable>
  );
}

export { Appear, Segmented } from "./motion";

export function EmptyState({
  icon,
  title,
  text,
  action,
}: {
  icon: IconName;
  title?: string;
  text: string;
  action?: { title: string; onPress: () => void };
}) {
  return (
    <View
      style={{
        alignItems: "center",
        gap: 10,
        paddingVertical: 24,
        paddingHorizontal: 20,
        borderRadius: 22,
        borderWidth: 1,
        borderStyle: "dashed",
        borderColor: colors.line,
      }}
    >
      <IconTile icon={icon} tone="gold" />
      {title && <Text style={[ui.h3, { textAlign: "center" }]}>{title}</Text>}
      <Text style={[ui.body, { textAlign: "center", fontSize: 14 }]}>
        {text}
      </Text>
      {action && <LinkButton title={action.title} onPress={action.onPress} />}
    </View>
  );
}

export function Field({
  label,
  ...props
}: ComponentProps<typeof TextInput> & { label: string }) {
  const [focused, setFocused] = useState(false);
  return (
    <View>
      <Text style={ui.label}>{label}</Text>
      <TextInput
        accessibilityLabel={label}
        placeholderTextColor={colors.faint}
        {...props}
        onFocus={(e) => {
          setFocused(true);
          props.onFocus?.(e);
        }}
        onBlur={(e) => {
          setFocused(false);
          props.onBlur?.(e);
        }}
        style={[
          ui.input,
          focused && { borderColor: colors.goldBright, borderWidth: 1.5 },
          props.style,
        ]}
      />
    </View>
  );
}
export function Message({
  text,
  error = false,
}: {
  text: string;
  error?: boolean;
}) {
  return text ? (
    <View
      accessibilityRole="alert"
      style={{
        flexDirection: "row",
        gap: 10,
        padding: 14,
        borderRadius: 16,
        backgroundColor: error ? colors.dangerBg : colors.pale,
      }}
    >
      <Ionicons
        name={error ? "alert-circle" : "information-circle"}
        size={19}
        color={error ? colors.danger : colors.green}
        style={{ marginTop: 1 }}
      />
      <Text
        style={{
          flex: 1,
          color: error ? colors.danger : colors.green,
          fontSize: 14,
          lineHeight: 21,
          fontFamily: fonts.sans,
        }}
      >
        {text}
      </Text>
    </View>
  ) : null;
}
export function Sheet({
  title,
  visible,
  onClose,
  children,
}: {
  title: string;
  visible: boolean;
  onClose: () => void;
  children: ReactNode;
}) {
  const insets = useSafeAreaInsets();
  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}
    >
      <View
        style={{
          flex: 1,
          backgroundColor: "rgba(18, 36, 28, 0.55)",
          justifyContent: "flex-end",
        }}
      >
        <View
          style={{
            backgroundColor: colors.bg,
            paddingHorizontal: 22,
            paddingTop: 10,
            paddingBottom: Math.max(insets.bottom, 24),
            borderTopLeftRadius: 30,
            borderTopRightRadius: 30,
            maxHeight: "92%",
            flexShrink: 1,
            width: "100%",
            maxWidth: 720,
            alignSelf: "center",
            boxShadow: shadow.raised,
          }}
        >
          <View
            style={{
              alignSelf: "center",
              width: 40,
              height: 5,
              borderRadius: 3,
              backgroundColor: colors.line,
              marginBottom: 14,
            }}
          />
          <View style={[ui.row, { marginBottom: 18 }]}>
            <Text style={[ui.h2, { flex: 1 }]}>{title}</Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Close dialog"
              onPress={onClose}
              hitSlop={12}
              style={{
                width: 32,
                height: 32,
                borderRadius: 16,
                backgroundColor: colors.sunken,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Ionicons name="close" size={18} color={colors.text} />
            </Pressable>
          </View>
          <KeyboardAwareScrollView
            style={{ flexGrow: 0, flexShrink: 1 }}
            bottomOffset={24}
            keyboardDismissMode="on-drag"
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={{ gap: 16, paddingBottom: 16 }}
          >
            {children}
          </KeyboardAwareScrollView>
        </View>
      </View>
    </Modal>
  );
}
export function Choice({
  label,
  value,
  options,
  onChange,
  disabled = false,
  emptyMessage = "No options available",
}: {
  label: string;
  value: string;
  options: { value: string; label: string }[];
  onChange: (value: string) => void;
  disabled?: boolean;
  emptyMessage?: string;
}) {
  const [open, setOpen] = useState(false);
  const unavailable = disabled || options.length === 0;
  const current = options.find((o) => o.value === value)?.label;
  return (
    <View>
      <Text style={ui.label}>{label}</Text>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={label}
        accessibilityState={{ disabled: unavailable }}
        disabled={unavailable}
        onPress={() => setOpen(true)}
        style={[ui.input, ui.row, { opacity: unavailable ? 0.5 : 1 }]}
      >
        <Text
          style={{
            color: current ? colors.text : colors.faint,
            flex: 1,
            fontSize: 16,
            fontFamily: fonts.sans,
          }}
        >
          {options.length === 0 ? emptyMessage : current || "Select an option"}
        </Text>
        <Ionicons name="chevron-down" color={colors.muted} size={17} />
      </Pressable>
      <Sheet title={label} visible={open} onClose={() => setOpen(false)}>
        <View style={ui.group}>
          {options.map((option, i) => {
            const selected = value === option.value;
            return (
              <Pressable
                key={option.value}
                accessibilityRole="radio"
                accessibilityState={{ selected }}
                accessibilityLabel={option.label}
                onPress={() => {
                  onChange(option.value);
                  setOpen(false);
                }}
                style={({ pressed }) => [
                  ui.row,
                  {
                    paddingVertical: 16,
                    paddingHorizontal: 16,
                    borderBottomWidth: i === options.length - 1 ? 0 : 1,
                    borderColor: colors.line,
                    backgroundColor: pressed ? colors.sunken : "transparent",
                  },
                ]}
              >
                <Text
                  style={{
                    color: colors.text,
                    flex: 1,
                    fontSize: 15,
                    fontWeight: selected ? "600" : "400",
                    fontFamily: fonts.sans,
                  }}
                >
                  {option.label}
                </Text>
                {selected && (
                  <Ionicons
                    name="checkmark-circle"
                    size={22}
                    color={colors.green}
                  />
                )}
              </Pressable>
            );
          })}
        </View>
      </Sheet>
    </View>
  );
}
export function Check({
  label,
  checked,
  onChange,
  disabled = false,
}: {
  label: string;
  checked: boolean;
  onChange: () => void;
  disabled?: boolean;
}) {
  return (
    <Pressable
      accessibilityRole="checkbox"
      accessibilityLabel={label}
      accessibilityState={{ checked, disabled }}
      disabled={disabled}
      onPress={() => {
        select();
        onChange();
      }}
      style={{
        flexDirection: "row",
        gap: 12,
        alignItems: "flex-start",
        paddingVertical: 8,
        opacity: disabled ? 0.5 : 1,
      }}
    >
      <View
        style={{
          width: 22,
          height: 22,
          borderRadius: 7,
          marginTop: 1,
          borderWidth: 1.5,
          borderColor: checked ? colors.green : colors.faint,
          backgroundColor: checked ? colors.green : colors.card,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        {checked && (
          <Ionicons name="checkmark" size={15} color={colors.onPrimary} />
        )}
      </View>
      <Text
        style={[
          ui.body,
          { flex: 1, color: colors.text, fontSize: 14, lineHeight: 21 },
        ]}
      >
        {label}
      </Text>
    </Pressable>
  );
}

/** Screen title block: gold eyebrow, display-serif title and optional intro. */
export function PageHeader({
  eyebrow,
  title,
  intro,
  icon,
}: {
  eyebrow?: string;
  title: string;
  intro?: string;
  icon?: IconName;
}) {
  return (
    <View style={{ gap: 8 }}>
      {eyebrow ? <Text style={ui.eyebrow}>{eyebrow}</Text> : null}
      <View style={[ui.row, { alignItems: "flex-start" }]}>
        <Text accessibilityRole="header" style={[ui.title, { flex: 1 }]}>
          {title}
        </Text>
        {icon && <IconTile icon={icon} tone="gold" size={48} />}
      </View>
      {intro ? <Text style={ui.body}>{intro}</Text> : null}
    </View>
  );
}

/** Previous / next paging controls; hidden when there is only one page. */
export function Pager({
  previous,
  next,
  hasPrevious,
  hasNext,
  disabled = false,
  page,
}: {
  previous: { title: string; onPress: () => void };
  next: { title: string; onPress: () => void };
  hasPrevious: boolean;
  hasNext: boolean;
  disabled?: boolean;
  page?: string;
}) {
  if (!hasPrevious && !hasNext) return null;
  return (
    <View style={[ui.row, { justifyContent: "center" }]}>
      <Button
        variant="ghost"
        icon="chevron-back"
        title={previous.title}
        disabled={disabled || !hasPrevious}
        onPress={previous.onPress}
      />
      {page ? <Text style={ui.caption}>{page}</Text> : null}
      <Button
        variant="ghost"
        title={next.title}
        disabled={disabled || !hasNext}
        onPress={next.onPress}
      />
    </View>
  );
}

/** Card for a locked or not-yet-connected area. */
export function NoticeCard({
  icon = "lock-closed-outline",
  title,
  text,
  children,
}: {
  icon?: IconName;
  title: string;
  text: string;
  children?: ReactNode;
}) {
  return (
    <View style={[ui.card, { alignItems: "flex-start" }]}>
      <IconTile icon={icon} tone="gold" />
      <Text style={ui.h2}>{title}</Text>
      <Text style={ui.body}>{text}</Text>
      {children}
    </View>
  );
}

/** Read-only detail row inside a `ui.group` card. */
export function InfoRow({
  icon,
  title,
  subtitle,
  last = false,
}: {
  icon: IconName;
  title: string;
  subtitle?: string;
  last?: boolean;
}) {
  return (
    <View
      style={[
        ui.row,
        {
          justifyContent: "flex-start",
          paddingVertical: 14,
          paddingHorizontal: 16,
          borderBottomWidth: last ? 0 : 1,
          borderColor: colors.line,
        },
      ]}
    >
      <IconTile icon={icon} size={38} />
      <View style={{ flex: 1, gap: 2 }}>
        <Text style={[ui.h3, { fontSize: 15 }]}>{title}</Text>
        {subtitle ? <Text style={ui.caption}>{subtitle}</Text> : null}
      </View>
    </View>
  );
}

export function Avatar({ name, size = 40 }: { name: string; size?: number }) {
  const initials = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]!.toUpperCase())
    .join("");
  return (
    <View
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
      style={{
        width: size,
        height: size,
        borderRadius: size / 2,
        backgroundColor: colors.dark,
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <Text
        style={{
          color: colors.goldBright,
          fontSize: size * 0.36,
          fontWeight: "700",
          fontFamily: fonts.sans,
        }}
      >
        {initials}
      </Text>
    </View>
  );
}

/** Visual switch row. The accessible name is the action it performs. */
export function ToggleRow({
  title,
  description,
  status,
  on,
  actionLabel,
  onPress,
  disabled = false,
  last = false,
}: {
  title: string;
  description?: string;
  status: string;
  on: boolean;
  actionLabel: string;
  onPress: () => void;
  disabled?: boolean;
  last?: boolean;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={actionLabel}
      accessibilityState={{ disabled, checked: on }}
      disabled={disabled}
      onPress={() => {
        select();
        onPress();
      }}
      style={({ pressed }) => [
        ui.row,
        {
          alignItems: "flex-start",
          padding: 16,
          backgroundColor: pressed ? colors.sunken : "transparent",
          borderBottomWidth: last ? 0 : 1,
          borderColor: colors.line,
          opacity: disabled ? 0.6 : 1,
        },
      ]}
    >
      <View style={{ flex: 1, gap: 4 }}>
        <Text style={ui.h3}>{title}</Text>
        {description ? (
          <Text style={[ui.body, { fontSize: 14, lineHeight: 20 }]}>
            {description}
          </Text>
        ) : null}
        <Text
          style={[
            ui.caption,
            { fontWeight: "600", color: on ? colors.success : colors.muted },
          ]}
        >
          {status}
        </Text>
      </View>
      <View
        style={{
          width: 48,
          height: 28,
          borderRadius: 14,
          padding: 3,
          marginTop: 2,
          backgroundColor: on ? colors.green : colors.line,
          alignItems: on ? "flex-end" : "flex-start",
        }}
      >
        <View
          style={{
            width: 22,
            height: 22,
            borderRadius: 11,
            backgroundColor: "#FFFFFF",
            boxShadow: shadow.card,
          }}
        />
      </View>
    </Pressable>
  );
}

/** Quiet "How this works" link that opens supporting detail in a sheet. */
export function InfoLink({
  title = "How this works",
  sheetTitle,
  paragraphs,
}: {
  title?: string;
  sheetTitle: string;
  paragraphs: string[];
}) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${title}: ${sheetTitle}`}
        onPress={() => setOpen(true)}
        hitSlop={8}
        style={({ pressed }) => ({
          flexDirection: "row",
          alignItems: "center",
          gap: 6,
          alignSelf: "flex-start",
          opacity: pressed ? 0.6 : 1,
        })}
      >
        <Ionicons
          name="information-circle-outline"
          size={17}
          color={colors.gold}
        />
        <Text
          style={{
            color: colors.gold,
            fontSize: 13,
            fontWeight: "600",
            fontFamily: fonts.sans,
          }}
        >
          {title}
        </Text>
      </Pressable>
      <Sheet title={sheetTitle} visible={open} onClose={() => setOpen(false)}>
        {paragraphs.map((text, i) => (
          <Text key={i} style={ui.body}>
            {text}
          </Text>
        ))}
        <Button
          title="Got it"
          variant="secondary"
          onPress={() => setOpen(false)}
        />
      </Sheet>
    </>
  );
}
