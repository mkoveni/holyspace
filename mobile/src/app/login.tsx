import { useState } from "react";
import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useSession } from "@/core/session";
import { Button, Field, Message, Screen, Sheet } from "@/ui/components";
import { ui } from "@/ui/theme";
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
    <Screen>
      <LinearGradient
        colors={["#244B3C", "#376B50"]}
        style={{
          padding: 28,
          borderRadius: 28,
          minHeight: 220,
          justifyContent: "space-between",
          overflow: "hidden",
        }}
      >
        <Ionicons name="sparkles-outline" size={35} color="#D3DCC7" />
        <View>
          <Text style={[ui.eyebrow, { color: "#CBCEA9", marginBottom: 14 }]}>
            A PLACE TO BELONG
          </Text>
          <Text style={[ui.title, { color: "#fff", fontSize: 36 }]}>
            Your church. A little closer.
          </Text>
        </View>
      </LinearGradient>
      <View style={{ gap: 10 }}>
        <Text style={ui.eyebrow}>WELCOME TO DIVINE COMMUNITY SPACE</Text>
        <Text style={ui.h2}>Make yourself at home.</Text>
        <Text style={ui.body}>
          Stay connected to your church, wherever life takes you.
        </Text>
      </View>
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
          busy={busy}
          disabled={!identifier.trim() || !password}
          onPress={() => void signIn()}
        />
        <Button
          title="Need help signing in?"
          subtle
          onPress={() => setHelp(true)}
        />
      </View>
      <Text style={[ui.body, { fontSize: 12, textAlign: "center" }]}>
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
