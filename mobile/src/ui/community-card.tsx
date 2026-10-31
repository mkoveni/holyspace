import { Pressable, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { router } from "expo-router";
import type { MemberCommunity } from "@/core/models";
import { communityRole } from "@/core/communities";
import { colors, ui } from "./theme";
export function CommunityCard({ community }: { community: MemberCommunity }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`Open ${community.name}`}
      style={ui.card}
      onPress={() =>
        router.push({
          pathname: "/community/[id]",
          params: { id: community.id },
        })
      }
    >
      <View style={ui.row}>
        <Text style={ui.eyebrow}>
          {community.kind === "MINISTRY" ? "MINISTRY" : "GROUP"}
        </Text>
        <Ionicons
          name={
            community.kind === "MINISTRY" ? "leaf-outline" : "people-outline"
          }
          size={25}
          color={colors.green}
        />
      </View>
      <Text style={ui.h2}>{community.name}</Text>
      {Boolean(community.campus) && (
        <Text style={ui.body}>{community.campus}</Text>
      )}
      {community.parentName && (
        <Text style={ui.body}>Part of {community.parentName}</Text>
      )}
      <View style={ui.row}>
        <Text style={ui.badge}>{communityRole(community.role)}</Text>
        <Ionicons name="arrow-forward" size={20} color={colors.green} />
      </View>
    </Pressable>
  );
}
