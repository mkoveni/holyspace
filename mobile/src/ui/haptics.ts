import { Platform } from "react-native";

type HapticsModule = typeof import("expo-haptics");

// Loaded lazily so a development build made before expo-haptics was added
// keeps working (without feedback) instead of failing at start-up.
let module: HapticsModule | null | undefined;
function haptics(): HapticsModule | null {
  if (Platform.OS === "web") return null;
  if (module === undefined) {
    try {
      // eslint-disable-next-line @typescript-eslint/no-require-imports
      module = require("expo-haptics") as HapticsModule;
    } catch {
      module = null;
    }
  }
  return module;
}

/** Light tap for primary actions. */
export function tap() {
  const h = haptics();
  h?.impactAsync(h.ImpactFeedbackStyle.Light).catch(() => {});
}

/** Tick for changing a selection (filters, switches, checkboxes). */
export function select() {
  haptics()
    ?.selectionAsync()
    .catch(() => {});
}

/** Success pulse after a confirmed outcome. */
export function success() {
  const h = haptics();
  h?.notificationAsync(h.NotificationFeedbackType.Success).catch(() => {});
}
