import { useState } from "react";
import type { MyCommunities } from "@/core/models";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import {
  Appear,
  EmptyState,
  Message,
  NoticeCard,
  PageHeader,
  Pager,
  Screen,
  Segmented,
} from "@/ui/components";
import { CommunityCard } from "@/ui/community-card";
import { SkeletonList } from "@/ui/skeleton";
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
      <PageHeader
        eyebrow="Rooted in community"
        title="Your people. Your purpose."
        intro="Your groups and ministries, and the moments that bring you together."
      />
      <Segmented
        value={kind}
        options={[
          { value: "", label: "All memberships" },
          { value: "GROUP", label: "Groups" },
          { value: "MINISTRY", label: "Ministries" },
        ]}
        onChange={filter}
      />
      <Message text={error} error />
      {!data && !error && <SkeletonList />}
      {data && !data.verified ? (
        <NoticeCard
          title="Let’s connect your community."
          text="Ask the church office to verify your member identity to see your groups and ministries."
        />
      ) : (
        data && (
          <>
            {!data.items.length && (
              <EmptyState
                icon="people-outline"
                title="There’s a place for you."
                text={`No current ${
                  kind === "GROUP"
                    ? "group"
                    : kind === "MINISTRY"
                      ? "ministry"
                      : "group or ministry"
                } memberships to show. Contact the church office if a membership is missing or you’d like to get involved.`}
              />
            )}
            {data.items.map((community, i) => (
              <Appear key={community.id} index={i}>
                <CommunityCard community={community} />
              </Appear>
            ))}
            <Pager
              hasPrevious={offset > 0}
              hasNext={data.hasMore}
              disabled={loading}
              previous={{
                title: "Previous memberships",
                onPress: () => setOffset(Math.max(0, offset - 25)),
              }}
              next={{
                title: "More memberships",
                onPress: () => setOffset(offset + 25),
              }}
            />
          </>
        )
      )}
    </Screen>
  );
}
