import { Appearance, Platform, StyleSheet } from "react-native";
const light = {
  // Surfaces
  bg: "#F7F4EC",
  card: "#FFFFFF",
  sunken: "#F1ECE0",
  // The arch logo is drawn for cream; it sits on this tile in both palettes.
  logoTile: "#F7F4EC",
  // Forest greens: hero panels, strong headings, primary fills
  hero: "#12241C",
  ink: "#12241C",
  dark: "#16302A",
  green: "#1F3A2F",
  heroCard: "#1E392E",
  heroLine: "#2F5143",
  // Text
  text: "#1A2A23",
  muted: "#5F6B63",
  faint: "#7A857E",
  onDark: "#F4EEDF",
  onDarkMuted: "#AEBBB2",
  onPrimary: "#FFFFFF",
  onGold: "#12241C",
  onDanger: "#FFFFFF",
  // Gold
  gold: "#806430",
  goldBright: "#C9A962",
  goldSoft: "#F4ECD9",
  // Lines and tints
  line: "#E8E2D4",
  pale: "#EAEFE6",
  successBg: "#E4EFE6",
  success: "#2D6A4A",
  warningBg: "#F7EBD6",
  warning: "#8A5A18",
  dangerBg: "#F8E7E4",
  danger: "#A63E36",
};
const dark: typeof light = {
  bg: "#0C1511",
  card: "#15211B",
  sunken: "#1B2A23",
  logoTile: "#F7F4EC",
  hero: "#102A20",
  ink: "#F2ECDD",
  dark: "#22392F",
  green: "#9DCBB1",
  heroCard: "#18372B",
  heroLine: "#2B5242",
  text: "#E8E3D5",
  muted: "#A7B2AA",
  faint: "#86928A",
  onDark: "#F4EEDF",
  onDarkMuted: "#AEBBB2",
  onPrimary: "#0C1511",
  onGold: "#12241C",
  onDanger: "#1A0B09",
  gold: "#D6B97A",
  goldBright: "#C9A962",
  goldSoft: "#2E2817",
  line: "#26372F",
  pale: "#1D3329",
  successBg: "#173326",
  success: "#86CFA5",
  warningBg: "#33280F",
  warning: "#E4B66A",
  dangerBg: "#3A1D1A",
  danger: "#F0948A",
};
/**
 * The palette follows the device appearance when the app starts. A change of
 * system appearance while the app is open applies on the next launch.
 */
export const scheme: "light" | "dark" =
  Appearance.getColorScheme() === "dark" ? "dark" : "light";
export const colors = scheme === "dark" ? dark : light;
export const fonts = {
  display: "Fraunces_500Medium",
  displayItalic: "Fraunces_500Medium_Italic",
  sans: Platform.select({
    web: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif",
    default: undefined,
  }),
};
export const shadow =
  scheme === "dark"
    ? {
        card: "0px 1px 2px rgba(0, 0, 0, 0.30), 0px 6px 18px rgba(0, 0, 0, 0.25)",
        raised:
          "0px 2px 4px rgba(0, 0, 0, 0.35), 0px 12px 28px rgba(0, 0, 0, 0.40)",
      }
    : {
        card: "0px 1px 2px rgba(18, 36, 28, 0.04), 0px 6px 18px rgba(18, 36, 28, 0.06)",
        raised:
          "0px 2px 4px rgba(18, 36, 28, 0.06), 0px 12px 28px rgba(18, 36, 28, 0.10)",
      };
export const ui = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: {
    padding: 20,
    paddingBottom: 40,
    gap: 20,
    width: "100%",
    maxWidth: 720,
    alignSelf: "center",
  },
  eyebrow: {
    fontSize: 11,
    letterSpacing: 1.6,
    fontWeight: "600",
    color: colors.gold,
    textTransform: "uppercase",
    fontFamily: fonts.sans,
  },
  title: {
    fontFamily: fonts.display,
    fontSize: 34,
    lineHeight: 40,
    letterSpacing: -0.6,
    color: colors.ink,
  },
  h2: {
    fontSize: 19,
    fontWeight: "600",
    color: colors.text,
    letterSpacing: -0.3,
    fontFamily: fonts.sans,
  },
  h3: {
    fontSize: 16,
    fontWeight: "600",
    color: colors.text,
    letterSpacing: -0.2,
    fontFamily: fonts.sans,
  },
  body: {
    fontSize: 15,
    lineHeight: 23,
    color: colors.muted,
    fontFamily: fonts.sans,
  },
  caption: {
    fontSize: 12,
    lineHeight: 17,
    color: colors.muted,
    fontFamily: fonts.sans,
  },
  card: {
    padding: 20,
    backgroundColor: colors.card,
    borderRadius: 22,
    borderWidth: 1,
    borderColor: colors.line,
    gap: 14,
    boxShadow: shadow.card,
  },
  group: {
    backgroundColor: colors.card,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: colors.line,
    overflow: "hidden",
    boxShadow: shadow.card,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  label: {
    fontSize: 13,
    color: colors.muted,
    fontWeight: "600",
    marginBottom: 8,
    fontFamily: fonts.sans,
  },
  input: {
    minHeight: 54,
    paddingHorizontal: 16,
    paddingVertical: 13,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 16,
    fontSize: 16,
    color: colors.text,
    backgroundColor: colors.card,
    fontFamily: fonts.sans,
  },
  separator: { height: 1, backgroundColor: colors.line, marginVertical: 4 },
  badge: {
    backgroundColor: colors.pale,
    color: colors.green,
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 999,
    fontSize: 11,
    fontWeight: "600",
    overflow: "hidden",
    fontFamily: fonts.sans,
  },
});
