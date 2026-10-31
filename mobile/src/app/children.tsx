import { useState } from "react";
import { Pressable, Text, View } from "react-native";
import { router } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import type { FamilyChild, FamilyPage } from "@/core/models";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen } from "@/ui/components";
import { colors, ui } from "@/ui/theme";
export default function Children() {
  const { api } = useSession(),
    [offset, setOffset] = useState(0);
  const { data, error, loading, reload } = useLoad(
    (key) =>
      api<FamilyPage<FamilyChild>>(
        `/api/member/children?limit=10&offset=${JSON.parse(key)[0]}`,
      ),
    [offset],
  );
  return (
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <Button
        title="Back to home"
        subtle
        onPress={() => router.navigate("/")}
      />
      <Text style={ui.eyebrow}>GROWING TOGETHER</Text>
      <Text style={ui.title}>My children & family ministry</Text>
      <Text style={ui.body}>
        A little reassurance for their time with the ministry team.
      </Text>
      <Message text={error} error />
      {data && !data.verified && (
        <Message text="Ask the church office to verify your member identity before viewing family ministry." />
      )}
      {data?.verified && !data.items.length && (
        <Message text="No children on this page. A safeguarding coordinator must verify your guardian authority. Being in the same household or having a parent relationship does not automatically grant ministry access." />
      )}
      {data?.items.map((child) => (
        <Pressable
          key={child.id}
          accessibilityRole="button"
          accessibilityLabel={`Open ${child.name}`}
          style={[ui.card, ui.row]}
          onPress={() =>
            router.push({ pathname: "/child/[id]", params: { id: child.id } })
          }
        >
          <View style={{ flex: 1, gap: 8 }}>
            <Ionicons name="flower-outline" size={28} color={colors.green} />
            <Text style={ui.h2}>{child.name}</Text>
            <Text style={ui.body}>Programmes, sessions & collection</Text>
          </View>
          <Ionicons name="chevron-forward" size={20} color={colors.muted} />
        </Pressable>
      ))}
      {data?.verified && (
        <View style={ui.row}>
          <Button
            title="Previous children"
            subtle
            disabled={loading || offset === 0}
            onPress={() => setOffset(Math.max(0, offset - 10))}
          />
          <Button
            title="More children"
            subtle
            disabled={loading || !data.hasMore}
            onPress={() => setOffset(offset + 10)}
          />
        </View>
      )}
      <Message text="Guardian access and pickup permission are separate. Staff will always check the collecting adult’s identity and current authorization." />
    </Screen>
  );
}
