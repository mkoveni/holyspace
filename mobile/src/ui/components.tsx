import { useState } from "react";
import type { ComponentProps, ReactNode } from "react";
import {
  ActivityIndicator,
  Image,
  Modal,
  Pressable,
  Text,
  TextInput,
  View,
} from "react-native";
import { KeyboardAwareScrollView } from "react-native-keyboard-controller";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Ionicons } from "@expo/vector-icons";
import { appName } from "@/core/brand";
import { colors, ui } from "./theme";
const logoMark = require("../../assets/images/logo-mark.png");
export function Screen({
  children,
  refreshing,
  onRefresh,
}: {
  children: ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
}) {
  const insets = useSafeAreaInsets();
  return (
    <View style={[ui.screen, { paddingTop: insets.top }]}>
      <View style={[ui.row, { paddingHorizontal: 24, paddingVertical: 18 }]}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            gap: 8,
            flexShrink: 1,
          }}
        >
          <Image
            source={logoMark}
            style={{ width: 28, height: 28 }}
            accessibilityIgnoresInvertColors
          />
          <Text
            numberOfLines={1}
            style={{
              flexShrink: 1,
              fontSize: 19,
              fontWeight: "600",
              letterSpacing: -0.5,
              color: colors.green,
            }}
          >
            {appName}
          </Text>
        </View>
        {onRefresh && (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Refresh"
            onPress={onRefresh}
            disabled={refreshing}
            hitSlop={12}
          >
            {refreshing ? (
              <ActivityIndicator color={colors.green} />
            ) : (
              <Ionicons name="refresh-outline" size={21} color={colors.green} />
            )}
          </Pressable>
        )}
      </View>
      <KeyboardAwareScrollView
        style={{ flex: 1 }}
        bottomOffset={24}
        keyboardDismissMode="on-drag"
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={[
          ui.content,
          { paddingBottom: 40 + insets.bottom },
        ]}
      >
        {children}
      </KeyboardAwareScrollView>
    </View>
  );
}
export function Button({
  title,
  onPress,
  busy = false,
  disabled = false,
  subtle = false,
  danger = false,
}: {
  title: string;
  onPress: () => void;
  busy?: boolean;
  disabled?: boolean;
  subtle?: boolean;
  danger?: boolean;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: disabled || busy, busy }}
      accessibilityLabel={title}
      onPress={onPress}
      disabled={disabled || busy}
      style={({ pressed }) => ({
        minHeight: 52,
        paddingVertical: 14,
        paddingHorizontal: 18,
        borderRadius: 15,
        backgroundColor: subtle
          ? colors.pale
          : danger
            ? colors.danger
            : colors.green,
        opacity: disabled || busy ? 0.45 : pressed ? 0.8 : 1,
        alignItems: "center",
        justifyContent: "center",
      })}
    >
      {busy ? (
        <ActivityIndicator color={subtle ? colors.green : "#fff"} />
      ) : (
        <Text
          style={{
            fontSize: 14,
            fontWeight: "600",
            color: subtle ? colors.green : "#fff",
            textAlign: "center",
          }}
        >
          {title}
        </Text>
      )}
    </Pressable>
  );
}
export function Field({
  label,
  ...props
}: ComponentProps<typeof TextInput> & { label: string }) {
  return (
    <View>
      <Text style={ui.label}>{label}</Text>
      <TextInput
        accessibilityLabel={label}
        placeholderTextColor="#A0AAA1"
        style={ui.input}
        {...props}
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
        padding: 16,
        borderRadius: 14,
        backgroundColor: error ? "#FAEDE9" : colors.pale,
      }}
    >
      <Text
        style={{
          color: error ? colors.danger : colors.green,
          fontSize: 14,
          lineHeight: 22,
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
          backgroundColor: "#12302777",
          justifyContent: "flex-end",
        }}
      >
        <View
          style={{
            backgroundColor: colors.bg,
            padding: 24,
            paddingBottom: Math.max(insets.bottom, 24),
            borderTopLeftRadius: 28,
            borderTopRightRadius: 28,
            maxHeight: "92%",
            flexShrink: 1,
            width: "100%",
            maxWidth: 720,
            alignSelf: "center",
          }}
        >
          <View style={[ui.row, { marginBottom: 22 }]}>
            <Text style={[ui.h2, { flex: 1 }]}>{title}</Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Close dialog"
              onPress={onClose}
              hitSlop={12}
            >
              <Ionicons name="close" size={24} color={colors.text} />
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
        <Text style={{ color: colors.text, flex: 1 }}>
          {options.length === 0
            ? emptyMessage
            : options.find((o) => o.value === value)?.label ||
              "Select an option"}
        </Text>
        <Ionicons name="chevron-down" color={colors.muted} size={17} />
      </Pressable>
      <Sheet title={label} visible={open} onClose={() => setOpen(false)}>
        {options.map((option) => (
          <Pressable
            key={option.value}
            accessibilityRole="radio"
            accessibilityState={{ selected: value === option.value }}
            accessibilityLabel={option.label}
            onPress={() => {
              onChange(option.value);
              setOpen(false);
            }}
            style={[
              ui.row,
              {
                paddingVertical: 15,
                borderBottomWidth: 1,
                borderColor: colors.line,
              },
            ]}
          >
            <Text style={{ color: colors.text, flex: 1 }}>{option.label}</Text>
            <Ionicons
              name={
                value === option.value ? "radio-button-on" : "radio-button-off"
              }
              size={22}
              color={colors.green}
            />
          </Pressable>
        ))}
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
      onPress={onChange}
      style={{
        flexDirection: "row",
        gap: 10,
        alignItems: "flex-start",
        paddingVertical: 8,
      }}
    >
      <Ionicons
        name={checked ? "checkbox" : "square-outline"}
        size={23}
        color={colors.green}
      />
      <Text
        style={[
          ui.body,
          { flex: 1, color: colors.text, fontSize: 13, lineHeight: 21 },
        ]}
      >
        {label}
      </Text>
    </Pressable>
  );
}
