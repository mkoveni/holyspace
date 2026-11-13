import { useState } from "react";
import { Text, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { randomUUID } from "expo-crypto";
import {
  cleanAnswers,
  firstMissing,
  toggleOption,
  typedAnswer,
} from "@/core/forms";
import type {
  Answers,
  FormResult,
  Leaderboard,
  MemberForm,
} from "@/core/forms";
import { useSession } from "@/core/session";
import { useLoad } from "@/hooks/use-load";
import { success } from "@/ui/haptics";
import {
  Badge,
  Button,
  Check,
  Field,
  Message,
  PageHeader,
  Screen,
} from "@/ui/components";
import { SkeletonList } from "@/ui/skeleton";
import { ui } from "@/ui/theme";
export default function FormScreen() {
  const { id } = useLocalSearchParams<{ id: string }>(),
    { api } = useSession();
  const { data, loading, error, reload } = useLoad(
    () => api<MemberForm>(`/api/member/forms/${id}`),
    [id],
  );
  const [answers, setAnswers] = useState<Answers>({}),
    [nickname, setNickname] = useState(""),
    [busy, setBusy] = useState(false),
    [problem, setProblem] = useState(""),
    [result, setResult] = useState<FormResult | null>(null),
    [board, setBoard] = useState<Leaderboard["entries"]>([]),
    // One key per form open: a retry after a dropped connection is not counted twice.
    [submissionKey] = useState(() => randomUUID());
  const set = (qid: string, value: unknown) =>
    setAnswers((a) => ({ ...a, [qid]: value }));
  async function submit() {
    if (!data || busy) return;
    const cleaned = cleanAnswers(answers),
      missing = firstMissing(data.questions, cleaned);
    if (missing) {
      setProblem(`Please answer: ${missing}`);
      return;
    }
    setBusy(true);
    setProblem("");
    try {
      const done = await api<FormResult>(
        `/api/member/forms/${id}/responses`,
        "POST",
        {
          answers: cleaned,
          formVersion: data.version,
          submissionKey,
          nickname: nickname.trim() || undefined,
        },
      );
      success();
      setResult(done);
      if (data.leaderboard) {
        try {
          setBoard(
            (await api<Leaderboard>(`/api/member/forms/${id}/leaderboard`))
              .entries,
          );
        } catch {
          setBoard([]);
        }
      }
    } catch (e) {
      setProblem(
        e instanceof Error ? e.message : "Unable to send your answers.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Screen
      refreshing={loading}
      onRefresh={() => void reload()}
      back={{ label: "All forms", onPress: () => router.navigate("/forms") }}
    >
      <Message text={error} error />
      {!data && !error && <SkeletonList count={2} />}
      {data && (
        <>
          <PageHeader
            eyebrow={data.kind === "QUIZ" ? "Quiz" : "Form"}
            title={data.title}
            intro={data.description || undefined}
          />
          {!data.open && (
            <Message text="This form is not taking answers right now." />
          )}
          {data.open && !result && (
            <View style={[ui.card, { gap: 16 }]}>
              <Message
                text={
                  data.identity === "ANONYMOUS"
                    ? `Your answers are anonymous and deleted after ${data.retentionMonths} months.`
                    : `Your name is recorded with your answers. They are deleted after ${data.retentionMonths} months.`
                }
              />
              {data.questions.map((q) => (
                <View key={q.id} style={{ gap: 8 }}>
                  {["SINGLE_CHOICE", "MULTIPLE_CHOICE"].includes(q.type) ? (
                    <>
                      <Text style={ui.label}>
                        {q.label}
                        {q.required ? " *" : ""}
                      </Text>
                      {q.options.map((o) => {
                        const multi = q.type === "MULTIPLE_CHOICE",
                          checked = multi
                            ? Array.isArray(answers[q.id]) &&
                              (answers[q.id] as string[]).includes(o.id)
                            : answers[q.id] === o.id;
                        return (
                          <Check
                            key={o.id}
                            label={o.label}
                            checked={checked}
                            onChange={() =>
                              set(
                                q.id,
                                multi
                                  ? toggleOption(answers[q.id], o.id)
                                  : o.id,
                              )
                            }
                          />
                        );
                      })}
                    </>
                  ) : q.type === "CHECKBOX" ? (
                    <Check
                      label={`${q.label}${q.required ? " *" : ""}`}
                      checked={answers[q.id] === true}
                      onChange={() => set(q.id, answers[q.id] !== true)}
                    />
                  ) : (
                    <Field
                      label={`${q.label}${q.required ? " *" : ""}`}
                      value={String(answers[q.id] ?? "")}
                      onChangeText={(t) => set(q.id, typedAnswer(q, t))}
                      multiline={q.type === "LONG_TEXT"}
                      maxLength={q.type === "LONG_TEXT" ? 4000 : 500}
                      keyboardType={
                        q.type === "NUMBER" || q.type === "RATING"
                          ? "numeric"
                          : q.type === "EMAIL"
                            ? "email-address"
                            : "default"
                      }
                      autoCapitalize={q.type === "EMAIL" ? "none" : "sentences"}
                      placeholder={
                        q.type === "RATING"
                          ? "1 to 5"
                          : q.type === "DATE"
                            ? "YYYY-MM-DD"
                            : undefined
                      }
                    />
                  )}
                </View>
              ))}
              {data.leaderboard && (
                <Field
                  label="Nickname for the leaderboard"
                  value={nickname}
                  onChangeText={setNickname}
                  maxLength={40}
                />
              )}
              <Message text={problem} error />
              <Button
                title="Submit"
                busy={busy}
                onPress={() => void submit()}
              />
            </View>
          )}
          {result && (
            <View style={[ui.card, { gap: 12 }]}>
              <Badge text="Answers received" tone="success" />
              {result.score !== null && result.maxScore !== null && (
                <Text style={ui.h2}>
                  You scored {result.score} out of {result.maxScore}
                </Text>
              )}
              {data.leaderboard && (
                <>
                  <Text style={ui.h2}>Leaderboard</Text>
                  {board.length === 0 && (
                    <Text style={ui.body}>No entries yet.</Text>
                  )}
                  {board.map((e) => (
                    <Text key={e.rank} style={ui.body}>
                      {e.rank}. {e.nickname}: {e.score}/{e.maxScore}
                    </Text>
                  ))}
                </>
              )}
            </View>
          )}
        </>
      )}
    </Screen>
  );
}
