import { useState } from "react";
import { Text, View } from "react-native";
import { useSession } from "@/core/session";
import { Button, Field, Message, Screen, Sheet } from "@/ui/components";
import { colors, ui } from "@/ui/theme";
export default function Login() {
  const session = useSession(),
    [identifier, setIdentifier] = useState(""),
    [password, setPassword] = useState(""),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [help, setHelp] = useState(false);
  async function signIn() {
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      await session.signIn(identifier, password);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to sign in.");
      setPassword("");
    } finally {
      setBusy(false);
    }
  }
  return (
    <Screen
      hero={
        <View style={{ gap: 12, paddingTop: 28, paddingBottom: 12 }}>
          <Text style={[ui.eyebrow, { color: colors.goldBright }]}>
            A PLACE TO BELONG
          </Text>
          <Text
            accessibilityRole="header"
            style={[
              ui.title,
              { color: colors.onDark, fontSize: 40, lineHeight: 46 },
            ]}
          >
            Your church.{"\n"}A little closer.
          </Text>
          <Text style={[ui.body, { color: colors.onDarkMuted }]}>
            Stay connected to your church, wherever life takes you.
          </Text>
        </View>
      }
    >
      <Message text={session.notice} />
      <Message text={error} error />
      <View style={ui.card}>
        <Field
          label="Email or username"
          value={identifier}
          onChangeText={setIdentifier}
          autoCapitalize="none"
          autoCorrect={false}
          textContentType="username"
          editable={!busy}
        />
        <Field
          label="Password"
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          textContentType="password"
          editable={!busy}
          onSubmitEditing={() => void signIn()}
        />
        <Button
          title="Sign in"
          variant="gold"
          busy={busy}
          disabled={!identifier.trim() || !password}
          onPress={() => void signIn()}
        />
        <Button
          title="Need help signing in?"
          variant="ghost"
          onPress={() => setHelp(true)}
        />
      </View>
      <Text style={[ui.caption, { textAlign: "center" }]}>
        Your account is provided by your church. Member and giving access are
        verified separately.
      </Text>
      <Sheet
        title="Let’s get you connected."
        visible={help}
        onClose={() => setHelp(false)}
      >
        <Text style={ui.body}>
          Contact your church office to create or recover your account. The team
          will verify your identity before resetting access. A password reset
          signs out existing devices.
        </Text>
        <Text style={ui.body}>
          Please share identity documents only through your church’s approved
          process.
        </Text>
        <Button title="Back to sign in" onPress={() => setHelp(false)} />
      </Sheet>
    </Screen>
  );
}
