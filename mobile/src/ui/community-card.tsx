import { Pressable, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { router } from "expo-router";
import type { MemberCommunity } from "@/core/models";
import { communityRole } from "@/core/communities";
import { Badge, IconTile } from "./components";
import { CoverThumb } from "./cover-image";
import { colors, ui } from "./theme";
export function CommunityCard({ community }: { community: MemberCommunity }) {
  const ministry = community.kind === "MINISTRY";
  const leads = community.role === "LEADER" || community.role === "CO_LEADER";
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`Open ${community.name}`}
      style={({ pressed }) => [
        ui.card,
        {
          flexDirection: "row",
          alignItems: "center",
          gap: 14,
          padding: 16,
          transform: [{ scale: pressed ? 0.99 : 1 }],
        },
      ]}
      onPress={() =>
        router.push({
          pathname: "/community/[id]",
          params: { id: community.id },
        })
      }
    >
      <CoverThumb
        url={community.coverImageUrl}
        size={48}
        fallback={
          <IconTile
            icon={ministry ? "leaf-outline" : "people-outline"}
            tone={ministry ? "gold" : "neutral"}
            size={48}
          />
        }
      />
      <View style={{ flex: 1, gap: 3 }}>
        <Text style={ui.eyebrow}>{ministry ? "MINISTRY" : "GROUP"}</Text>
        <Text style={ui.h3}>{community.name}</Text>
        {Boolean(community.campus) && (
          <Text style={ui.caption}>{community.campus}</Text>
        )}
        {Boolean(community.parentName) && (
          <Text style={ui.caption}>Part of {community.parentName}</Text>
        )}
        <View style={{ marginTop: 4 }}>
          <Badge
            text={communityRole(community.role)}
            tone={leads ? "gold" : "neutral"}
          />
        </View>
      </View>
      <Ionicons name="chevron-forward" size={18} color={colors.faint} />
    </Pressable>
  );
}
