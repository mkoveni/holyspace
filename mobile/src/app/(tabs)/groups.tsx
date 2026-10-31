import { useState } from "react";
import { Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import type { MyCommunities } from "@/core/models";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { Button, Message, Screen } from "@/ui/components";
import { CommunityCard } from "@/ui/community-card";
import { colors, ui } from "@/ui/theme";
export default function GroupsScreen() {
  const { api } = useSession();
  const [kind, setKind] = useState(""),
    [offset, setOffset] = useState(0);
  const { data, loading, error, reload } = useLoad(
    () =>
      api<MyCommunities>(
        `/api/member/communities?limit=25&offset=${offset}${kind ? `&kind=${kind}` : ""}`,
      ),
    [kind, offset],
  );
  function filter(value: string) {
    setOffset(0);
    setKind(value);
  }
  return (
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <View style={{ gap: 10 }}>
        <Text style={ui.eyebrow}>ROOTED IN COMMUNITY</Text>
        <Text style={ui.title}>Your people. Your purpose.</Text>
        <Text style={ui.body}>
          Your groups and ministries, and the moments that bring you together.
        </Text>
      </View>
      <View
        style={[ui.row, { flexWrap: "wrap", justifyContent: "flex-start" }]}
      >
        {[
          { value: "", title: "All memberships" },
          { value: "GROUP", title: "Groups" },
          { value: "MINISTRY", title: "Ministries" },
        ].map((option) => (
          <Button
            key={option.value}
            title={option.title}
            subtle={kind !== option.value}
            onPress={() => filter(option.value)}
          />
        ))}
      </View>
      <Message text={error} error />
      {data && !data.verified ? (
        <View style={ui.card}>
          <Ionicons name="lock-closed-outline" color={colors.green} size={30} />
          <Text style={ui.h2}>Let’s connect your community.</Text>
          <Text style={ui.body}>
            Ask the church office to verify your member identity to see your
            groups and ministries.
          </Text>
        </View>
      ) : (
        data && (
          <>
            {!data.items.length && (
              <View style={ui.card}>
                <Ionicons
                  name="people-outline"
                  size={30}
                  color={colors.green}
                />
                <Text style={ui.h2}>There’s a place for you.</Text>
                <Text style={ui.body}>
                  No current{" "}
                  {kind === "GROUP"
                    ? "group"
                    : kind === "MINISTRY"
                      ? "ministry"
                      : "group or ministry"}{" "}
                  memberships to show. Contact the church office if a membership
                  is missing or you’d like to get involved.
                </Text>
              </View>
            )}
            {data.items.map((community) => (
              <CommunityCard key={community.id} community={community} />
            ))}
            {(offset > 0 || data.hasMore) && (
              <View style={ui.row}>
                <Button
                  title="Previous memberships"
                  subtle
                  disabled={offset === 0 || loading}
                  onPress={() => setOffset(Math.max(0, offset - 25))}
                />
                <Button
                  title="More memberships"
                  subtle
                  disabled={!data.hasMore || loading}
                  onPress={() => setOffset(offset + 25)}
                />
              </View>
            )}
          </>
        )
      )}
    </Screen>
  );
}
