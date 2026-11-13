import assert from "node:assert/strict";
import { test } from "node:test";
import {
  cleanAnswers,
  firstMissing,
  toggleOption,
  typedAnswer,
} from "../src/core/forms.ts";
import type { FormQuestion } from "../src/core/forms.ts";
const q = (over: Partial<FormQuestion>): FormQuestion => ({
  id: "a",
  type: "TEXT",
  label: "Name",
  required: true,
  options: [],
  ...over,
});
test("blank answers are dropped", () => {
  assert.deepEqual(
    cleanAnswers({ a: "", b: [], c: null, d: false, e: 0, f: "x" }),
    {
      d: false,
      e: 0,
      f: "x",
    },
  );
});
test("a required question needs an answer and a required checkbox must be ticked", () => {
  assert.equal(firstMissing([q({})], {}), "Name");
  assert.equal(firstMissing([q({})], { a: "Sam" }), null);
  assert.equal(
    firstMissing([q({ type: "CHECKBOX", label: "Agree" })], { a: false }),
    "Agree",
  );
  assert.equal(firstMissing([q({ required: false })], {}), null);
});
test("number answers are numbers and bad input is blank", () => {
  assert.equal(typedAnswer(q({ type: "NUMBER" }), "4.5"), 4.5);
  assert.equal(typedAnswer(q({ type: "NUMBER" }), "abc"), "");
  assert.equal(typedAnswer(q({}), "hi"), "hi");
});
test("multiple choice toggles", () => {
  assert.deepEqual(toggleOption(undefined, "x"), ["x"]);
  assert.deepEqual(toggleOption(["x", "y"], "x"), ["y"]);
});
