export type QuestionType =
  | "TEXT"
  | "LONG_TEXT"
  | "EMAIL"
  | "NUMBER"
  | "RATING"
  | "DATE"
  | "SINGLE_CHOICE"
  | "MULTIPLE_CHOICE"
  | "CHECKBOX";
export type FormQuestion = {
  id: string;
  type: QuestionType;
  label: string;
  required: boolean;
  options: { id: string; label: string }[];
};
export type MemberFormSummary = {
  id: string;
  title: string;
  description: string;
  kind: "DATA_COLLECTION" | "QUIZ";
  identity: "ANONYMOUS" | "IDENTIFIED";
  closesAt: string | null;
};
export type MemberForm = {
  id: string;
  title: string;
  description: string;
  kind: "DATA_COLLECTION" | "QUIZ";
  identity: "ANONYMOUS" | "IDENTIFIED";
  version: number;
  questions: FormQuestion[];
  open: boolean;
  retentionMonths: number;
  leaderboard: boolean;
};
export type FormResult = {
  received: boolean;
  score: number | null;
  maxScore: number | null;
  answers: Record<string, unknown> | null;
};
export type Leaderboard = {
  entries: {
    rank: number;
    nickname: string;
    score: number;
    maxScore: number;
  }[];
};
export type Answers = Record<string, unknown>;
function blank(value: unknown): boolean {
  return (
    value === undefined ||
    value === null ||
    value === "" ||
    (Array.isArray(value) && value.length === 0)
  );
}
/** Drops unanswered questions so an optional blank is not sent. */
export function cleanAnswers(answers: Answers): Answers {
  return Object.fromEntries(
    Object.entries(answers).filter(([, value]) => !blank(value)),
  );
}
/** The label of the first required question without an answer, or null. The server checks again. */
export function firstMissing(
  questions: FormQuestion[],
  answers: Answers,
): string | null {
  for (const q of questions) {
    if (!q.required) continue;
    const value = answers[q.id];
    if (blank(value) || (q.type === "CHECKBOX" && value !== true))
      return q.label;
  }
  return null;
}
/** Turns what a text box holds into the value the API expects for the question. */
export function typedAnswer(q: FormQuestion, text: string): unknown {
  if (q.type === "NUMBER") {
    const n = Number(text);
    return text.trim() === "" || !Number.isFinite(n) ? "" : n;
  }
  return text;
}
export function toggleOption(current: unknown, id: string): string[] {
  const list = Array.isArray(current) ? (current as string[]) : [];
  return list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
}
