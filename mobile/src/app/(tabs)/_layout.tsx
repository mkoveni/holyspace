import { Tabs } from "expo-router/js-tabs";
import { Ionicons } from "@expo/vector-icons";
import { colors } from "@/ui/theme";
const icons: Record<string, keyof typeof Ionicons.glyphMap> = {
  index: "home-outline",
  giving: "heart-outline",
  events: "calendar-outline",
  groups: "people-outline",
  profile: "person-outline",
};
export default function TabLayout() {
  return (
    <Tabs
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarHideOnKeyboard: true,
        tabBarActiveTintColor: colors.green,
        tabBarInactiveTintColor: "#8C978F",
        tabBarStyle: {
          backgroundColor: colors.bg,
          borderTopColor: colors.line,
          minHeight: 72,
          paddingTop: 9,
          paddingBottom: 9,
        },
        tabBarLabelStyle: { fontSize: 10, fontWeight: "600", marginTop: 4 },
        tabBarIcon: ({ color, size }) => (
          <Ionicons
            name={icons[route.name]}
            color={typeof color === "string" ? color : colors.green}
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
