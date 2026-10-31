import { test } from "node:test";
import assert from "node:assert/strict";
import { zarMinor } from "../src/core/money.ts";
test("giving amounts use exact cents and enforce supported bounds", () => {
  assert.equal(zarMinor("150.50"), 15050);
  assert.equal(zarMinor("5"), 500);
  assert.equal(zarMinor("1000000.00"), 100000000);
  for (const value of ["4.99", "1000000.01", "10.999", "1e3", "-10", "NaN", ""])
    assert.equal(zarMinor(value), null, value);
});
