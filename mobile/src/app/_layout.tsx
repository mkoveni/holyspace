import { DevicePushProvider } from "@/core/device-push";
import {
  Fraunces_500Medium,
  Fraunces_500Medium_Italic,
} from "@expo-google-fonts/fraunces";
import { useFonts } from "expo-font";
import { Stack } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { ActivityIndicator, View, Text } from "react-native";
import { KeyboardProvider } from "react-native-keyboard-controller";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { SessionProvider, useSession } from "@/core/session";
import { appName } from "@/core/brand";
import { Button, Message } from "@/ui/components";
import { colors, ui } from "@/ui/theme";
function Routes() {
  const session = useSession();
  const [fontsLoaded, fontError] = useFonts({
    Fraunces_500Medium,
    Fraunces_500Medium_Italic,
  });
  if (!session.ready || (!fontsLoaded && !fontError))
    return (
      <View
        style={[
          ui.screen,
          {
            justifyContent: "center",
            alignItems: "center",
            backgroundColor: colors.hero,
          },
        ]}
      >
        <ActivityIndicator color={colors.goldBright} size="large" />
        <Text style={[ui.body, { marginTop: 16, color: colors.onDarkMuted }]}>
          Opening {appName}…
        </Text>
      </View>
    );
  if (session.error)
    return (
      <View
        style={[ui.screen, { justifyContent: "center", padding: 28, gap: 20 }]}
      >
        <Message text={session.error} error />
        <Button
          title="Try connection again"
          onPress={() => void session.retry()}
        />
        <Button
          title="Return to sign in"
          subtle
          onPress={() => void session.clear()}
        />
      </View>
    );
  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.bg },
      }}
    >
      <Stack.Protected guard={!session.account}>
        <Stack.Screen name="login" />
      </Stack.Protected>
      <Stack.Protected guard={Boolean(session.account)}>
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="event/[id]" />
        <Stack.Screen name="community/[id]" />
        <Stack.Screen name="meeting/[id]" />
        <Stack.Screen name="security" />
        <Stack.Screen name="volunteering" />
        <Stack.Screen name="children" />
        <Stack.Screen name="inbox" />
        <Stack.Screen name="notification-preferences" />
        <Stack.Screen name="child/[id]" />
        <Stack.Screen name="payment/[id]" options={{ presentation: "modal" }} />
      </Stack.Protected>
    </Stack>
  );
}
export default function Layout() {
  return (
    <SafeAreaProvider>
      <KeyboardProvider preload={false}>
        <SessionProvider>
          <DevicePushProvider>
            <StatusBar style="auto" />
            <Routes />
          </DevicePushProvider>
        </SessionProvider>
      </KeyboardProvider>
    </SafeAreaProvider>
  );
}
