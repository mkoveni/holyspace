import { useState } from "react";
import { Pressable, Text, View } from "react-native";
import { router } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import type { FamilyChild, FamilyPage } from "@/core/models";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import {
  Avatar,
  EmptyState,
  Message,
  NoticeCard,
  PageHeader,
  Pager,
  Screen,
} from "@/ui/components";
import { colors, ui } from "@/ui/theme";
import { SkeletonList } from "@/ui/skeleton";
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
    <Screen
      refreshing={loading}
      onRefresh={() => void reload()}
      back={{ label: "Back to home", onPress: () => router.navigate("/") }}
    >
      <PageHeader
        eyebrow="Growing together"
        title="My children & family ministry"
        intro="A little reassurance for their time with the ministry team."
      />
      <Message text={error} error />
      {!data && !error && <SkeletonList count={2} />}
      {data && !data.verified && (
        <NoticeCard
          title="Let’s connect your family."
          text="Ask the church office to verify your member identity before viewing family ministry."
        />
      )}
      {data?.verified && !data.items.length && (
        <EmptyState
          icon="flower-outline"
          text="No children on this page. A safeguarding coordinator must verify your guardian authority. Being in the same household or having a parent relationship does not automatically grant ministry access."
        />
      )}
      {data?.items.map((child) => (
        <Pressable
          key={child.id}
          accessibilityRole="button"
          accessibilityLabel={`Open ${child.name}`}
          style={({ pressed }) => [
            ui.card,
            ui.row,
            { padding: 16, transform: [{ scale: pressed ? 0.99 : 1 }] },
          ]}
          onPress={() =>
            router.push({ pathname: "/child/[id]", params: { id: child.id } })
          }
        >
          <Avatar name={child.name} size={48} />
          <View style={{ flex: 1, gap: 3 }}>
            <Text style={ui.h3}>{child.name}</Text>
            <Text style={ui.caption}>Programmes, sessions & collection</Text>
          </View>
          <Ionicons name="chevron-forward" size={18} color={colors.faint} />
        </Pressable>
      ))}
      {data?.verified && (
        <Pager
          hasPrevious={offset > 0}
          hasNext={data.hasMore}
          disabled={loading}
          previous={{
            title: "Previous children",
            onPress: () => setOffset(Math.max(0, offset - 10)),
          }}
          next={{
            title: "More children",
            onPress: () => setOffset(offset + 10),
          }}
        />
      )}
      <Text style={ui.caption}>
        Guardian access and pickup permission are separate. Staff will always
        check the collecting adult’s identity and current authorization.
      </Text>
    </Screen>
  );
}
