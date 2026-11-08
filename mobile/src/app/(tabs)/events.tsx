import { useState } from "react";
import { useSession } from "@/core/session";
import type { MemberEvents } from "@/core/models";
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
import { EventCard } from "@/ui/event-card";
import { SkeletonList } from "@/ui/skeleton";
export default function EventsScreen() {
  const { api } = useSession();
  const [mine, setMine] = useState(false),
    [offset, setOffset] = useState(0);
  const { data, loading, error, reload } = useLoad(
    () =>
      api<MemberEvents>(
        `/api/member/events?mine=${mine}&limit=25&offset=${offset}`,
      ),
    [mine, offset],
  );
  function choose(value: boolean) {
    setOffset(0);
    setMine(value);
  }
  return (
    <Screen refreshing={loading} onRefresh={() => void reload()}>
      <PageHeader
        eyebrow="Gather. Belong. Grow."
        title="Make room for community."
        intro="Find your next gathering and keep your plans close. Upcoming six months."
      />
      <Segmented
        value={mine ? "mine" : "all"}
        options={[
          { value: "all", label: "Discover events" },
          { value: "mine", label: "My bookings" },
        ]}
        onChange={(v) => choose(v === "mine")}
      />
      <Message text={error} error />
      {!data && !error && <SkeletonList />}
      {data && !data.verified ? (
        <NoticeCard
          title="Let’s connect your events."
          text="Ask the church office to verify your member identity before browsing or booking events."
        />
      ) : (
        data && (
          <>
            {!data.items.length && (
              <EmptyState
                icon="calendar-outline"
                title={
                  mine
                    ? "Your next chapter awaits."
                    : "More moments are on their way."
                }
                text={
                  mine
                    ? "You have no upcoming bookings. Explore the church calendar to find your next gathering."
                    : "There are no published gatherings in the next six months. Check back soon."
                }
                action={
                  mine
                    ? { title: "Discover events", onPress: () => choose(false) }
                    : undefined
                }
              />
            )}
            {data.items.map((event, i) => (
              <Appear key={event.id} index={i}>
                <EventCard event={event} />
              </Appear>
            ))}
            <Pager
              hasPrevious={offset > 0}
              hasNext={data.hasMore}
              disabled={loading}
              previous={{
                title: "Previous events",
                onPress: () => setOffset(Math.max(0, offset - 25)),
              }}
              next={{
                title: "More events",
                onPress: () => setOffset(offset + 25),
              }}
            />
          </>
        )
      )}
    </Screen>
  );
}
