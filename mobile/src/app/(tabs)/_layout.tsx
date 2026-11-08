import { Tabs } from "expo-router/js-tabs";
import { Ionicons } from "@expo/vector-icons";
import { colors, fonts } from "@/ui/theme";
type IconName = keyof typeof Ionicons.glyphMap;
const icons: Record<string, [IconName, IconName]> = {
  index: ["home-outline", "home"],
  giving: ["heart-outline", "heart"],
  events: ["calendar-outline", "calendar"],
  groups: ["people-outline", "people"],
  profile: ["person-outline", "person"],
};
export default function TabLayout() {
  return (
    <Tabs
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarHideOnKeyboard: true,
        tabBarActiveTintColor: colors.ink,
        tabBarInactiveTintColor: colors.muted,
        tabBarStyle: {
          backgroundColor: colors.card,
          borderTopColor: colors.line,
          minHeight: 72,
          paddingTop: 8,
          paddingBottom: 9,
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: "600",
          marginTop: 3,
          fontFamily: fonts.sans,
        },
        tabBarIcon: ({ color, size, focused }) => (
          <Ionicons
            name={icons[route.name][focused ? 1 : 0]}
            color={
              focused
                ? colors.green
                : typeof color === "string"
                  ? color
                  : colors.muted
            }
            size={size}
          />
        ),
      })}
    >
      <Tabs.Screen name="index" options={{ title: "Home" }} />
      <Tabs.Screen name="events" options={{ title: "Events" }} />
      <Tabs.Screen name="groups" options={{ title: "Groups" }} />
      <Tabs.Screen name="giving" options={{ title: "Give" }} />
      <Tabs.Screen name="profile" options={{ title: "My profile" }} />
    </Tabs>
  );
}
