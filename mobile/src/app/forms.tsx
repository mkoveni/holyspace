import { router } from "expo-router";
import type { MemberFormSummary } from "@/core/forms";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import {
  EmptyState,
  ListRow,
  Message,
  PageHeader,
  Screen,
} from "@/ui/components";
import { SkeletonList } from "@/ui/skeleton";
import { View } from "react-native";
import { ui } from "@/ui/theme";
export default function Forms() {
  const { api } = useSession();
  const { data, loading, error, reload } = useLoad(() =>
    api<{ items: MemberFormSummary[] }>("/api/member/forms"),
  );
  return (
    <Screen
      refreshing={loading}
      onRefresh={() => void reload()}
      back={{ label: "Back to home", onPress: () => router.navigate("/") }}
    >
      <PageHeader
        eyebrow="Have your say"
        title="Forms and quizzes"
        intro="Forms your church has invited you to answer."
      />
      <Message text={error} error />
      {!data && !error && <SkeletonList count={2} />}
      {data && !data.items.length && (
        <EmptyState
          icon="document-text-outline"
          text="There are no forms for you to answer right now."
        />
      )}
      {data && data.items.length > 0 && (
        <View style={ui.card}>
          {data.items.map((form, i) => (
            <ListRow
              key={form.id}
              icon={
                form.kind === "QUIZ" ? "help-circle-outline" : "create-outline"
              }
              title={form.title}
              subtitle={form.description || undefined}
              last={i === data.items.length - 1}
              onPress={() =>
                router.navigate({
                  pathname: "/form/[id]",
                  params: { id: form.id },
                })
              }
            />
          ))}
        </View>
      )}
    </Screen>
  );
}
