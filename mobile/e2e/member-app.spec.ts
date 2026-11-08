import type { Page } from "@playwright/test";
import { expect, test } from "@playwright/test";
const accountId = "8e6d0291-aa92-433b-b60d-83a867d21001",
  personId = "8e6d0291-aa92-433b-b60d-83a867d21002",
  orderId = "8e6d0291-aa92-433b-b60d-83a867d21003";
async function fixture(page: Page, linked = true, offeredFunds = true) {
  const errors: string[] = [],
    requests: { path: string; method: string; body: any }[] = [];
  page.on("pageerror", (e) => errors.push(e.message));
  const profile = {
    id: personId,
    details: {
      titleCode: "MR",
      firstName: "Solly",
      lastName: "Mabasa",
      dateOfBirth: "1990-02-10",
      gender: "MALE",
    },
    membershipType: "ACTIVE_MEMBER",
    version: 0,
    contacts: [
      {
        id: "contact-1",
        kind: "EMAIL",
        value: "solly@example.org",
        primary: true,
      },
    ],
  };
  const order = {
    id: orderId,
    fundId: "fund-1",
    amountMinor: 10000,
    provider: "yoco",
    mode: "test",
    recurring: false,
    status: "PAID",
    subscriptionStatus: "NONE",
    version: 1,
    createdAt: "2026-10-28T10:00:00Z",
  };
  const giving = {
    donor: linked ? { id: personId, name: "Solly Mabasa" } : null,
    funds: [
      {
        id: "fund-1",
        name: "General giving",
        description: "Supporting the life of our church.",
      },
    ],
    providers: [
      {
        id: "yoco",
        name: "Yoco",
        mode: "test",
        available: true,
        recurring: false,
      },
    ],
    payments: { items: [order], total: 1 },
    gifts: [
      {
        id: "gift-1",
        amountMinor: 25000,
        currency: "ZAR",
        receivedOn: "2026-10-27",
        method: "BANK",
        reversed: false,
        refundedMinor: 0,
      },
    ],
    giftTotal: 1,
  };
  let uncertain = false;
  await page.route("**/api/**", async (route) => {
    const req = route.request(),
      path = new URL(req.url()).pathname,
      method = req.method(),
      body = req.postDataJSON();
    requests.push({ path, method, body });
    const headers = {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
      "Access-Control-Allow-Methods": "GET,POST,PUT,DELETE,OPTIONS",
    };
    const respond = (json: any, status = 200) =>
      route.fulfill({ json, status, headers });
    if (method === "OPTIONS") return route.fulfill({ status: 204, headers });
    if (path === "/api/login_check")
      return respond({ token: "mobile-e2e-session" });
    if (path === "/api/me")
      return respond({
        id: accountId,
        displayName: "Solly Mabasa",
        email: "account@example.org",
        username: "solly",
        status: "active",
      });
    if (path === "/api/member/profile" && method === "GET")
      return respond({
        profile: linked ? profile : null,
        titles: [{ code: "MR", label: "Mr" }],
      });
    if (path === "/api/member/profile" && method === "PUT") {
      Object.assign(profile.details, {
        firstName: body.firstName,
        lastName: body.lastName,
        titleCode: body.titleCode,
        gender: body.gender,
      });
      profile.version++;
      return route.fulfill({ status: 204, headers });
    }
    if (path === "/api/member/profile/contacts" && method === "PUT") {
      profile.contacts.push({
        id: "contact-2",
        kind: body.kind,
        value: body.value,
        primary: true,
      });
      profile.version++;
      return respond({ id: "contact-2" }, 201);
    }
    if (path === "/api/member/push")
      return respond({
        available: false,
        projectId: null,
        preferences: [
          { topic: "VOLUNTEERING", enabled: false, version: 0 },
          { topic: "EVENTS", enabled: false, version: 0 },
        ],
        devices: [],
      });
    if (path === "/api/member/notifications")
      return respond({ items: [], unreadCount: 0, hasMore: false });
    if (path === "/api/member/group-meetings")
      return respond({ verified: linked, items: [], hasMore: false });
    if (path === "/api/member/volunteering")
      return respond({
        permitted: false,
        volunteerId: null,
        linked: false,
        status: null,
        version: null,
        weekly: [],
        exceptions: [],
        items: [],
        hasMore: false,
      });
    if (path === "/api/member/events")
      return respond({ verified: linked, items: [], hasMore: false });
    if (path === "/api/my-giving")
      return respond({ ...giving, funds: offeredFunds ? giving.funds : [] });
    if (path === "/api/my-giving/payments" && method === "POST") {
      if (uncertain) {
        uncertain = false;
        return respond(
          { message: "The outcome is uncertain. Retry the same gift request." },
          503,
        );
      }
      order.status = "PENDING";
      order.amountMinor = body.amountMinor;
      return respond({ id: orderId }, 201);
    }
    if (path === `/api/my-giving/payments/${orderId}`)
      return respond({
        order,
        charges:
          order.status === "PAID"
            ? [
                {
                  id: "charge-1",
                  amountMinor: 10000,
                  refundedMinor: 0,
                  receivedOn: "2026-10-28",
                  givingStatus: "SIMULATION",
                  confirmationStatus: "NOT_ISSUED",
                  captured: false,
                  confirmation: null,
                },
              ]
            : [],
        refunds: [],
      });
    if (path.endsWith("/mobile-checkout"))
      return respond(
        {
          message:
            "Provider is temporarily unavailable. Resume this same payment.",
        },
        503,
      );
    if (path === "/api/me/sessions" && method === "GET")
      return respond({
        items: [
          {
            id: "device-current",
            deviceName: "Divine Community Space · Android",
            current: true,
            createdAt: "2026-10-28T10:00:00Z",
            expiresAt: "2026-10-28T18:00:00Z",
          },
          {
            id: "device-other",
            deviceName: "Web browser",
            current: false,
            createdAt: "2026-10-27T10:00:00Z",
            expiresAt: "2026-10-29T18:00:00Z",
          },
        ],
        hasMore: false,
      });
    if (path.startsWith("/api/me/sessions/") && method === "DELETE")
      return route.fulfill({ status: 204, headers });
    if (path === "/api/me/password" && method === "PUT")
      return route.fulfill({ status: 204, headers });
    if (path === "/api/logout") return route.fulfill({ status: 204, headers });
    errors.push(`Unexpected API: ${method} ${path}`);
    return respond({ message: "Unexpected endpoint" }, 500);
  });
  return {
    errors,
    requests,
    uncertain: () => {
      uncertain = true;
    },
  };
}
async function signIn(page: Page) {
  await page.goto("/login");
  await page.getByLabel("Email or username", { exact: true }).fill("solly");
  await page.getByLabel("Password", { exact: true }).fill("a-private-password");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(
    page.getByText(/Good (morning|afternoon|evening), /),
  ).toBeVisible();
}
async function tab(page: Page, name: string) {
  await page.getByRole("tab", { name: new RegExp(name) }).click();
}
test("Member sign-in and home are usable on a phone and retain no browser credentials", async ({
  page,
}) => {
  const { errors, requests } = await fixture(page);
  await signIn(page);
  await expect(
    page.getByText(/Good (morning|afternoon|evening), Solly/),
  ).toBeVisible();
  expect(
    requests.find((r) => r.path === "/api/login_check")?.body.deviceName,
  ).toBe("Divine Community Space · Web preview");
  expect(await page.evaluate(() => localStorage.length)).toBe(0);
  await expect
    .poll(() =>
      page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
    )
    .toBe(true);
  await page.screenshot({
    path: "test-results/divinecommunityspace-home-390.png",
    fullPage: true,
  });
  expect(errors).toEqual([]);
});
test("Own profile edits preserve protected fields and contact changes use the current revision", async ({
  page,
}) => {
  const { errors, requests } = await fixture(page);
  await signIn(page);
  await tab(page, "My profile");
  await page
    .getByRole("button", { name: "Edit my personal details", exact: true })
    .click();
  await page.getByLabel("First name", { exact: true }).fill("Solly Updated");
  await page
    .getByRole("button", { name: "Save personal details", exact: true })
    .click();
  await expect(
    page.getByText("Solly Updated Mabasa", { exact: true }),
  ).toBeVisible();
  const saved = requests.find(
    (r) => r.path === "/api/member/profile" && r.method === "PUT",
  )?.body;
  expect(saved).toMatchObject({
    firstName: "Solly Updated",
    expectedVersion: 0,
  });
  expect(saved.dateOfBirth).toBeUndefined();
  expect(saved.membershipType).toBeUndefined();
  await page
    .getByRole("button", { name: "Add contact details", exact: true })
    .click();
  await page.getByLabel("Phone number", { exact: true }).fill("+27821234567");
  await page
    .getByRole("button", { name: "Save contact details", exact: true })
    .click();
  await expect(page.getByText("+27821234567", { exact: true })).toBeVisible();
  expect(
    requests.find((r) => r.path.endsWith("/profile/contacts"))?.body,
  ).toMatchObject({ kind: "PHONE", expectedVersion: 1 });
  expect(errors).toEqual([]);
});
test("Unlinked members see an explicit verification path and no profile-edit or give action", async ({
  page,
}) => {
  const { errors } = await fixture(page, false);
  await signIn(page);
  await tab(page, "My profile");
  await expect(page.getByText("Let’s connect your profile.")).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Edit my personal details", exact: true }),
  ).toHaveCount(0);
  await tab(page, "Give");
  await expect(page.getByText("Let’s connect your giving.")).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Review my gift", exact: true }),
  ).toHaveCount(0);
  await tab(page, "Events");
  await expect(
    page.getByText("Let’s connect your events.", { exact: true }),
  ).toBeVisible();
  expect(errors).toEqual([]);
});
test("Giving keeps uncertain intent stable and uses own server-derived identity", async ({
  page,
}) => {
  const { errors, requests, uncertain } = await fixture(page);
  uncertain();
  await signIn(page);
  await tab(page, "Give");
  await page.getByRole("button", { name: "Give towards", exact: true }).click();
  await page
    .getByRole("radio", { name: "General giving", exact: true })
    .click();
  await page.getByLabel("Amount (ZAR)", { exact: true }).fill("150.50");
  await page
    .getByRole("checkbox", {
      name: "I have checked the amount and selected fund.",
      exact: true,
    })
    .click();
  await page
    .getByRole("button", { name: "Review my gift", exact: true })
    .click();
  await expect(
    page.getByLabel("Amount (ZAR)", { exact: true }),
  ).not.toBeEditable();
  await page
    .getByRole("button", { name: "Retry same gift request", exact: true })
    .click();
  await expect(
    page.getByRole("button", { name: "Open secure checkout", exact: true }),
  ).toBeVisible();
  const starts = requests.filter(
    (r) => r.path === "/api/my-giving/payments" && r.method === "POST",
  );
  expect(starts).toHaveLength(2);
  expect(starts[0]!.body).toEqual(starts[1]!.body);
  expect(starts[0]!.body).toMatchObject({
    fundId: "fund-1",
    amountMinor: 15050,
    provider: "yoco",
    recurring: false,
  });
  expect(starts[0]!.body.donorId).toBeUndefined();
  await page
    .getByRole("button", { name: "Open secure checkout", exact: true })
    .click();
  await expect(
    page.getByText(
      "Provider is temporarily unavailable. Resume this same payment.",
    ),
  ).toBeVisible();
  expect(errors).toEqual([]);
});
test("Device revocation and password change require explicit actions", async ({
  page,
}) => {
  const { errors, requests } = await fixture(page);
  await signIn(page);
  await tab(page, "Home");
  await page
    .getByRole("button", { name: "Account & devices", exact: true })
    .click();
  await expect(page.getByText("This device", { exact: true })).toBeVisible();
  await page
    .getByRole("button", { name: "Revoke device access", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Confirm device revocation", exact: true })
    .click();
  expect(
    requests.some(
      (r) =>
        r.path === "/api/me/sessions/device-other" && r.method === "DELETE",
    ),
  ).toBe(true);
  await page
    .getByRole("button", { name: "Change my password", exact: true })
    .click();
  await page
    .getByLabel("Current password", { exact: true })
    .fill("old-password");
  await page
    .getByLabel("New password", { exact: true })
    .fill("A-new-password-2026");
  await page
    .getByLabel("Confirm new password", { exact: true })
    .fill("A-new-password-2026");
  await page
    .getByRole("button", { name: "Update password", exact: true })
    .click();
  await expect(
    page.getByRole("button", { name: "Sign in", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByText("Password changed. Sign in again with your new password."),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test("A revoked session clears private screens and returns to sign-in", async ({
  page,
}) => {
  const { errors } = await fixture(page);
  await signIn(page);
  await page.route("**/api/member/profile", (route) =>
    route.fulfill({
      status: 401,
      json: { message: "Session revoked" },
      headers: { "Access-Control-Allow-Origin": "*" },
    }),
  );
  await tab(page, "My profile");
  await expect(
    page.getByRole("button", { name: "Sign in", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByText("Your session expired. Please sign in again."),
  ).toBeVisible();
  await expect(page.getByText("Solly Mabasa", { exact: true })).toHaveCount(0);
  expect(errors).toEqual([]);
});

test("Giving explains missing online funds and keeps history available", async ({
  page,
}) => {
  const { errors, requests } = await fixture(page, true, false);
  await signIn(page);
  await tab(page, "Give");
  await expect(
    page.getByText(/No funds are available for online giving yet/),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Give towards", exact: true }),
  ).toBeDisabled();
  await expect(
    page.getByText("No funds available", { exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Review my gift", exact: true }),
  ).toBeDisabled();
  await expect(
    page.getByText("Your online gifts", { exact: true }),
  ).toBeVisible();
  expect(
    requests.filter(
      (r) => r.path === "/api/my-giving/payments" && r.method === "POST",
    ),
  ).toHaveLength(0);
  expect(errors).toEqual([]);
});

const coverImageUrl =
  "/api/public/cover-images/0b6f8f1e-6c1a-4d2e-9f3a-2b7c8d9e0f12";
// A 1x1 PNG, served the way the Symfony API serves cover images.
const coverPng = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
  "base64",
);
async function eventFixture(page: Page, full = false, cover = false) {
  const base = await fixture(page);
  const coverRequests: { url: string; authorization?: string }[] = [];
  await page.route("**/api/public/cover-images/**", (route) => {
    coverRequests.push({
      url: route.request().url(),
      authorization: route.request().headers()["authorization"],
    });
    return route.fulfill({
      status: 200,
      body: coverPng,
      headers: {
        "Content-Type": "image/png",
        "Access-Control-Allow-Origin": "*",
        "Cache-Control": "public, max-age=31536000, immutable",
      },
    });
  });
  const gathering = {
    id: "f6ef4648-3ca1-4b08-8fde-4e04440c6511",
    eventId: "f6ef4648-3ca1-4b08-8fde-4e04440c6512",
    title: "A Sunday together",
    description: "A place to worship and belong.",
    startsAt: new Date(Date.now() + 1800000).toISOString(),
    endsAt: new Date(Date.now() + 7200000).toISOString(),
    timeZone: "Africa/Johannesburg",
    venueName: "Main hall",
    venueAddress: "10 Church Road",
    status: "SCHEDULED",
    registrationOpen: true,
    waitlistEnabled: true,
    remainingPlaces: full ? 0 : 10,
    booking: null as null | {
      id: string;
      status: string;
      version: number;
      attendance: string | null;
    },
    coverImageUrl: cover ? coverImageUrl : null,
  };
  const bookingRequests: any[] = [];
  let uncertain = false;
  await page.route("**/api/member/events**", async (route) => {
    const request = route.request(),
      url = new URL(request.url()),
      body = request.postDataJSON();
    const headers = {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
      "Access-Control-Allow-Methods": "GET,POST,OPTIONS",
    };
    const respond = (json: unknown, status = 200) =>
      route.fulfill({ json, status, headers });
    if (request.method() === "OPTIONS")
      return route.fulfill({ status: 204, headers });
    if (url.pathname === "/api/member/events")
      return respond({
        verified: true,
        items:
          url.searchParams.get("mine") === "true" && !gathering.booking
            ? []
            : [gathering],
        hasMore: false,
      });
    if (request.method() === "GET") return respond(gathering);
    if (url.pathname.endsWith("/registration")) {
      bookingRequests.push(body);
      if (uncertain) {
        uncertain = false;
        return respond({ message: "The outcome is uncertain." }, 503);
      }
      gathering.booking = {
        id: "booking-1",
        status: full ? "WAITLISTED" : "CONFIRMED",
        version: gathering.booking ? gathering.booking.version + 1 : 0,
        attendance: null,
      };
      return respond({ id: "booking-1" }, 201);
    }
    if (url.pathname.endsWith("/cancel")) {
      expect(body.expectedVersion).toBe(gathering.booking!.version);
      gathering.booking!.status = "CANCELLED";
      gathering.booking!.version++;
      return route.fulfill({ status: 204, headers });
    }
    if (url.pathname.endsWith("/attendance-pass"))
      return respond({
        credential:
          "divinecommunityspace:event:v1:abcdefghijklmnopqrstuvwxyz0123456789",
        expiresAt: new Date(Date.now() + 300000).toISOString(),
      });
    throw new Error("Unexpected member event route");
  });
  return {
    ...base,
    gathering,
    bookingRequests,
    coverRequests,
    uncertainBooking: () => {
      uncertain = true;
    },
  };
}

test("Members browse events, book, display a QR and cancel explicitly", async ({
  page,
}) => {
  const { errors, bookingRequests, coverRequests } = await eventFixture(page);
  await signIn(page);
  await tab(page, "Events");
  await page
    .getByRole("button", { name: "View A Sunday together", exact: true })
    .click();
  await expect(page.getByText("10 Church Road", { exact: true })).toBeVisible();
  await expect(page.getByRole("img", { name: /^Cover image for/ })).toHaveCount(
    0,
  );
  expect(coverRequests).toEqual([]);
  await page
    .getByRole("button", { name: "Reserve my place", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Confirm my booking", exact: true })
    .click();
  await expect(
    page.getByText("Your booking · confirmed", { exact: true }),
  ).toBeVisible();
  expect(bookingRequests[0].personId).toBeUndefined();
  await page
    .getByRole("button", { name: "Show attendance code", exact: true })
    .click();
  await expect(
    page.getByRole("img", { name: "Event attendance QR code", exact: true }),
  ).toBeVisible();
  await page.screenshot({
    path: "test-results/member-event-390.png",
    fullPage: true,
  });
  await page
    .getByRole("button", { name: "Cancel my booking", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Confirm cancellation", exact: true })
    .click();
  await expect(
    page.getByText("Your booking · Cancelled", { exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("img", { name: "Event attendance QR code", exact: true }),
  ).toHaveCount(0);
  await page
    .getByRole("button", { name: "Back to events", exact: true })
    .click();
  await tab(page, "Home");
  await expect(
    page.getByText("Your upcoming bookings will appear here.", { exact: true }),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test("Event covers load from the API's public image route without credentials", async ({
  page,
}) => {
  const { errors, coverRequests } = await eventFixture(page, false, true);
  await signIn(page);
  await tab(page, "Events");
  await expect(
    page.getByRole("button", { name: "View A Sunday together", exact: true }),
  ).toBeVisible();
  await expect.poll(() => coverRequests.length).toBeGreaterThan(0);
  await page
    .getByRole("button", { name: "View A Sunday together", exact: true })
    .click();
  await expect(
    page.getByRole("img", {
      name: "Cover image for A Sunday together",
      exact: true,
    }),
  ).toBeVisible();
  await page.screenshot({
    path: "test-results/member-event-cover-390.png",
    fullPage: true,
  });
  for (const request of coverRequests) {
    const url = new URL(request.url);
    expect(url.pathname).toBe(coverImageUrl);
    expect(url.search).toBe("");
    expect(request.authorization).toBeUndefined();
  }
  expect(errors).toEqual([]);
});

test("A full event offers a waitlist without an attendance credential", async ({
  page,
}) => {
  const { errors } = await eventFixture(page, true);
  await signIn(page);
  await tab(page, "Events");
  await page
    .getByRole("button", { name: "View A Sunday together", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Join waitlist", exact: true })
    .click();
  await expect(
    page.getByText(/You are requesting a waitlist place/),
  ).toBeVisible();
  await page
    .getByRole("button", { name: "Confirm my booking", exact: true })
    .click();
  await expect(
    page.getByText("Your booking · waitlisted", { exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Show attendance code", exact: true }),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test("Uncertain event registration retains the reviewed request identifier", async ({
  page,
}) => {
  const { errors, bookingRequests, uncertainBooking } =
    await eventFixture(page);
  uncertainBooking();
  await signIn(page);
  await tab(page, "Events");
  await page
    .getByRole("button", { name: "View A Sunday together", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Reserve my place", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Confirm my booking", exact: true })
    .click();
  await expect(
    page.getByText(
      /The church service is temporarily unavailable|The outcome is uncertain/,
    ),
  ).toBeVisible();
  await page
    .getByRole("button", { name: "Confirm my booking", exact: true })
    .click();
  await expect(
    page.getByText("Your booking · confirmed", { exact: true }),
  ).toBeVisible();
  expect(bookingRequests).toHaveLength(2);
  expect(bookingRequests[0]).toEqual(bookingRequests[1]);
  expect(errors).toEqual([]);
});

test("Home shows own upcoming bookings and attendance QR disappears on expiry", async ({
  page,
}) => {
  const { errors, gathering } = await eventFixture(page);
  gathering.booking = {
    id: "booking-1",
    status: "CONFIRMED",
    version: 0,
    attendance: null,
  };
  await signIn(page);
  await page
    .getByRole("button", { name: "View A Sunday together", exact: true })
    .click();
  await page.clock.install();
  await page
    .getByRole("button", { name: "Show attendance code", exact: true })
    .click();
  await expect(
    page.getByRole("img", { name: "Event attendance QR code", exact: true }),
  ).toBeVisible();
  await page.clock.fastForward(301000);
  await expect(
    page.getByRole("img", { name: "Event attendance QR code", exact: true }),
  ).toHaveCount(0);
  await expect(
    page.getByText(
      "This code has expired. Generate a new code when you reach the welcome team.",
      { exact: true },
    ),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

async function communityFixture(page: Page, linked = true) {
  const base = await fixture(page, linked),
    requests: { path: string; method: string; query: string }[] = [];
  const group = {
    id: "my-group",
    kind: "GROUP",
    name: "Home circle",
    description: "Growing in faith together.",
    campus: "Central",
    parentName: "Care ministry",
    joinedAt: "2025-01-01T00:00:00Z",
    role: "MEMBER",
    coverImageUrl: null as string | null,
  };
  const ministry = {
    ...group,
    id: "my-ministry",
    kind: "MINISTRY",
    name: "Welcome team",
    parentName: null,
    role: "CO_LEADER",
  };
  const meeting = {
    id: "my-meeting",
    communityId: group.id,
    communityName: group.name,
    startsAt: new Date(Date.now() + 3600000).toISOString(),
    endsAt: new Date(Date.now() + 7200000).toISOString(),
    timeZone: "Africa/Johannesburg",
    venue: "Main hall",
    status: "OPEN",
    attendance: "UNRECORDED",
  };
  let active = true;
  await page.route(
    /\/api\/member\/(communities|group-meetings)/,
    async (route) => {
      const request = route.request(),
        url = new URL(request.url());
      requests.push({
        path: url.pathname,
        method: request.method(),
        query: url.search,
      });
      const headers = {
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
        "Access-Control-Allow-Methods": "GET,OPTIONS",
      };
      const respond = (json: unknown, status = 200) =>
        route.fulfill({ json, status, headers });
      if (request.method() === "OPTIONS")
        return route.fulfill({ status: 204, headers });
      if (url.pathname === "/api/member/communities") {
        const kind = url.searchParams.get("kind"),
          offset = Number(url.searchParams.get("offset") || 0);
        return respond({
          verified: linked,
          items:
            !linked || !active
              ? []
              : offset
                ? [ministry]
                : [group, ministry].filter(
                    (item) => !kind || item.kind === kind,
                  ),
          hasMore: linked && active && !offset && !kind,
        });
      }
      if (url.pathname === "/api/member/group-meetings")
        return respond({
          verified: linked,
          items:
            linked &&
            active &&
            url.searchParams.get("communityId") !== ministry.id
              ? [meeting]
              : [],
          hasMore: false,
        });
      if (!linked || !active)
        return respond(
          { message: "This community is no longer available." },
          404,
        );
      if (url.pathname.endsWith("/my-meeting")) return respond(meeting);
      if (url.pathname.endsWith("/my-group"))
        return respond({
          community: group,
          leaders: Number(url.searchParams.get("leaderOffset") || 0)
            ? [{ name: "Ayanda Mokoena", role: "CO_LEADER" }]
            : [{ name: "Naledi Mabasa", role: "LEADER" }],
          hasMoreLeaders: !Number(url.searchParams.get("leaderOffset") || 0),
        });
      if (url.pathname.endsWith("/my-ministry"))
        return respond({
          community: ministry,
          leaders: [],
          hasMoreLeaders: false,
        });
      throw Error("Unexpected own community request");
    },
  );
  return {
    ...base,
    requests,
    group,
    ministry,
    meeting,
    revokeMembership: () => {
      active = false;
    },
  };
}

test("My groups shows current ministries, leaders and only personal meeting attendance", async ({
  page,
}) => {
  const { errors, requests } = await communityFixture(page);
  await signIn(page);
  await expect(
    page.getByRole("button", { name: /View meeting for Home circle,/ }),
  ).toBeVisible();
  await tab(page, "Groups");
  await expect(page.getByRole("tab")).toHaveCount(5);
  await expect(
    page.getByRole("button", { name: "Open Welcome team", exact: true }),
  ).toBeVisible();
  await page.screenshot({
    path: "test-results/my-groups-390.png",
    fullPage: true,
  });
  await page
    .getByRole("button", { name: "Open Home circle", exact: true })
    .click();
  await expect(page.getByText("Naledi Mabasa", { exact: true })).toBeVisible();
  await expect(
    page
      .getByText("Part of Care ministry", { exact: true })
      .filter({ visible: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Open Care ministry", exact: true }),
  ).toHaveCount(0);
  await page.getByRole("button", { name: "More leaders", exact: true }).click();
  await expect(page.getByText("Ayanda Mokoena", { exact: true })).toBeVisible();
  await page
    .getByRole("button", { name: /View meeting for Home circle,/ })
    .click();
  await expect(
    page.getByText(
      "You are on the attendance list, but no attendance has been recorded yet.",
      { exact: true },
    ),
  ).toBeVisible();
  await expect(
    page.getByRole("button", {
      name: /Check in|Mark present|Prepare roster|Join group/,
    }),
  ).toHaveCount(0);
  expect(
    requests.every(
      (request) => request.method === "GET" || request.method === "OPTIONS",
    ),
  ).toBe(true);
  expect(errors).toEqual([]);
});

test("Community filters and membership pagination retain their own scope", async ({
  page,
}) => {
  const { errors, requests } = await communityFixture(page);
  await signIn(page);
  await tab(page, "Groups");
  await page.getByRole("button", { name: "Ministries", exact: true }).click();
  await expect(
    page.getByRole("button", { name: "Open Welcome team", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Open Home circle", exact: true }),
  ).toHaveCount(0);
  await page
    .getByRole("button", { name: "All memberships", exact: true })
    .click();
  await page
    .getByRole("button", { name: "More memberships", exact: true })
    .click();
  await expect(
    page.getByRole("button", { name: "Previous memberships", exact: true }),
  ).toBeEnabled();
  expect(requests.some((request) => request.query.includes("offset=25"))).toBe(
    true,
  );
  expect(errors).toEqual([]);
});

test("Unverified members get a clear groups verification path", async ({
  page,
}) => {
  const { errors } = await communityFixture(page, false);
  await signIn(page);
  await tab(page, "Groups");
  await expect(
    page.getByText("Let’s connect your community.", { exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Open Home circle", exact: true }),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test("Meeting attendance distinguishes missing preparation, omission and cancellation", async ({
  page,
}) => {
  const { errors, meeting } = await communityFixture(page);
  meeting.attendance = "NOT_PREPARED";
  await signIn(page);
  await page
    .getByRole("button", { name: /View meeting for Home circle,/ })
    .click();
  await expect(
    page.getByText(
      "The attendance list has not been prepared yet. This does not mean you are absent.",
      { exact: true },
    ),
  ).toBeVisible();
  meeting.attendance = "NOT_LISTED";
  await page.getByRole("button", { name: "Refresh", exact: true }).click();
  await expect(
    page.getByText("Not on the prepared attendance list", { exact: true }),
  ).toBeVisible();
  meeting.attendance = "PRESENT";
  meeting.status = "CANCELLED";
  await page.getByRole("button", { name: "Refresh", exact: true }).click();
  await expect(
    page.getByText("This meeting has been cancelled.", { exact: true }),
  ).toBeVisible();
  await expect(
    page.getByText("Your group leader has recorded you as present.", {
      exact: true,
    }),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test("Ending membership clears previously loaded community details on refresh", async ({
  page,
}) => {
  const { errors, revokeMembership } = await communityFixture(page);
  await signIn(page);
  await tab(page, "Groups");
  await page
    .getByRole("button", { name: "Open Home circle", exact: true })
    .click();
  await expect(page.getByText("Naledi Mabasa", { exact: true })).toBeVisible();
  revokeMembership();
  await page.getByRole("button", { name: "Refresh", exact: true }).click();
  await expect(
    page.getByText("This community is no longer available.", { exact: true }),
  ).toBeVisible();
  await expect(page.getByText("Naledi Mabasa", { exact: true })).toHaveCount(0);
  await expect(
    page.getByText("Growing in faith together.", { exact: true }),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test("Future event QR explains when it opens and enables at the admission boundary", async ({
  page,
}) => {
  const { errors, gathering } = await eventFixture(page);
  const start = Date.now() + 86400000;
  gathering.startsAt = new Date(start).toISOString();
  gathering.endsAt = new Date(start + 3600000).toISOString();
  gathering.booking = {
    id: "booking-1",
    status: "CONFIRMED",
    version: 0,
    attendance: null,
  };
  await signIn(page);
  await page
    .getByRole("button", { name: "View A Sunday together", exact: true })
    .click();
  await expect(
    page.getByText(/Your attendance code opens .*Africa\/Johannesburg/),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Show attendance code", exact: true }),
  ).toBeDisabled();
  await page.clock.install({ time: new Date(start - 7200000 - 1000) });
  await page.clock.fastForward(2000);
  await expect(
    page.getByRole("button", { name: "Show attendance code", exact: true }),
  ).toBeEnabled();
  await expect(
    page.getByText(/Your attendance code is available now/),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

async function volunteeringFixture(
  page: Page,
  state: "linked" | "unlinked" | "denied" = "linked",
) {
  const base = await fixture(page),
    requests: { path: string; method: string; body: any }[] = [];
  const duty = {
    id: "own-duty",
    status: "PROPOSED",
    shiftStatus: "OPEN",
    team: "Welcome team",
    role: "Host",
    activity: "Sunday gathering",
    location: "Main hall",
    startsAt: new Date(Date.now() + 86400000).toISOString(),
    endsAt: new Date(Date.now() + 90000000).toISOString(),
    readiness: {
      ready: false,
      issues: ["Screening must be valid through the end of this shift."],
    },
    invitation: {
      id: "own-invitation",
      version: 2,
      status: "PENDING",
      expiresAt: new Date(Date.now() + 3600000).toISOString(),
    },
  };
  let version = 3,
    weekly: unknown[] = [],
    exceptions: unknown[] = [],
    stale = false;
  await page.route(/\/api\/member\/volunteering/, async (route) => {
    const req = route.request(),
      url = new URL(req.url()),
      body = req.postDataJSON();
    const headers = {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
      "Access-Control-Allow-Methods": "GET,POST,PUT,OPTIONS",
    };
    const respond = (json: unknown, status = 200) =>
      route.fulfill({ json, status, headers });
    if (req.method() === "OPTIONS")
      return route.fulfill({ status: 204, headers });
    requests.push({ path: url.pathname, method: req.method(), body });
    if (req.method() === "GET")
      return respond({
        permitted: state !== "denied",
        volunteerId: state === "linked" ? "own-volunteer" : null,
        linked: state === "linked",
        status: "ACTIVE",
        version,
        weekly,
        exceptions,
        items:
          state === "linked"
            ? url.searchParams.get("offset") === "10"
              ? []
              : [duty]
            : [],
        hasMore: state === "linked" && url.searchParams.get("offset") !== "10",
      });
    if (url.pathname.endsWith("/respond")) {
      expect(body).toEqual({ expectedVersion: 2, decision: "DECLINED" });
      duty.status = "CANCELLED";
      duty.invitation.status = "DECLINED";
      return route.fulfill({ status: 204, headers });
    }
    if (url.pathname.endsWith("/availability")) {
      if (stale)
        return respond(
          {
            message:
              "Availability changed. Close and refresh before editing again.",
          },
          409,
        );
      expect(body.volunteerId).toBe("own-volunteer");
      expect(body.expectedVersion).toBe(3);
      weekly = body.weekly;
      exceptions = body.exceptions;
      version++;
      return route.fulfill({ status: 204, headers });
    }
    throw new Error("Unexpected volunteering route");
  });
  return {
    ...base,
    requests,
    stale: () => {
      stale = true;
    },
  };
}
test("My volunteering shows readiness, lets me decline an invitation and pages my duties", async ({
  page,
}) => {
  const { errors, requests } = await volunteeringFixture(page);
  await signIn(page);
  await expect(page.getByText("Your next duty", { exact: true })).toBeVisible();
  await page
    .getByRole("button", { name: "My volunteering", exact: true })
    .click();
  await expect(
    page
      .getByText("Sunday gathering", { exact: false })
      .filter({ visible: true }),
  ).toBeVisible();
  await expect(
    page
      .getByText("Screening must be valid through the end of this shift.")
      .filter({ visible: true }),
  ).toBeVisible();
  await page
    .getByRole("button", { name: "Respond to invitation", exact: true })
    .filter({ visible: true })
    .click();
  await page.getByRole("button", { name: "Decline duty", exact: true }).click();
  await expect(
    page.getByText(
      "Invitation declined. Your coordinator can plan another volunteer.",
    ),
  ).toBeVisible();
  await expect(
    page.getByText("CANCELLED", { exact: true }).filter({ visible: true }),
  ).toBeVisible();
  await page.getByRole("button", { name: "More duties" }).click();
  await expect(page.getByText("Page 2")).toBeVisible();
  await expect(
    page.getByRole("button", { name: "More duties" }),
  ).toBeDisabled();
  expect(requests.filter((r) => r.method === "POST")).toHaveLength(1);
  expect(errors).toEqual([]);
});
test("My volunteering saves unavailable dates through an explicit versioned review", async ({
  page,
}) => {
  const { errors, requests } = await volunteeringFixture(page);
  await signIn(page);
  await page
    .getByRole("button", { name: "My volunteering", exact: true })
    .click();
  await page.getByRole("button", { name: "Manage availability" }).click();
  await page.getByRole("button", { name: "Add unavailable dates" }).click();
  await page
    .getByRole("button", { name: "Add to availability", exact: true })
    .click();
  await expect(page.getByText("Unavailable", { exact: true })).toBeVisible();
  await page
    .getByRole("button", { name: "Save availability", exact: true })
    .click();
  await expect(
    page.getByText("Availability saved. Existing duties have not changed."),
  ).toBeVisible();
  const write = requests.find((r) => r.method === "PUT");
  expect(write?.body.expectedVersion).toBe(3);
  expect(write?.body.exceptions).toHaveLength(1);
  expect(write?.body.exceptions[0].available).toBe(false);
  await page.getByRole("button", { name: "Manage availability" }).click();
  await page.getByRole("button", { name: "Add weekly window" }).click();
  await page.getByRole("button", { name: /From date:/ }).click();
  await expect(
    page.getByRole("button", { name: "Next month", exact: true }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Close dialog" }).last().click();
  await page
    .getByRole("button", { name: "Add to availability", exact: true })
    .click();
  await expect(page.getByText(/Monday · 08:00/)).toBeVisible();
  await page.getByRole("button", { name: "Cancel changes" }).click();
  expect(requests.filter((r) => r.method === "PUT")).toHaveLength(1);
  expect(errors).toEqual([]);
  await page.screenshot({
    path: "test-results/my-volunteering-390.png",
    fullPage: true,
  });
});
test("My volunteering keeps a stale availability draft visible without automatic retries", async ({
  page,
}) => {
  const { errors, requests, stale } = await volunteeringFixture(page);
  await signIn(page);
  await page
    .getByRole("button", { name: "My volunteering", exact: true })
    .click();
  await page.getByRole("button", { name: "Manage availability" }).click();
  stale();
  await page
    .getByRole("button", { name: "Save availability", exact: true })
    .click();
  await expect(
    page.getByText(
      "Availability changed. Close and refresh before editing again.",
    ),
  ).toBeVisible();
  expect(requests.filter((r) => r.method === "PUT")).toHaveLength(1);
  expect(errors).toEqual([]);
});
test("My volunteering explains missing volunteer links and serving access", async ({
  page,
}) => {
  const { errors } = await volunteeringFixture(page, "unlinked");
  await signIn(page);
  await page
    .getByRole("button", { name: "My volunteering", exact: true })
    .click();
  await expect(
    page.getByText(/Your volunteer profile is not connected/),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Manage availability" }),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

test("My volunteering never offers actions without serving access", async ({
  page,
}) => {
  const { errors, requests } = await volunteeringFixture(page, "denied");
  await signIn(page);
  await page
    .getByRole("button", { name: "My volunteering", exact: true })
    .click();
  await expect(
    page.getByText(/enable volunteer self-service access/),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Manage availability" }),
  ).toHaveCount(0);
  expect(requests.every((r) => r.method === "GET")).toBe(true);
  expect(errors).toEqual([]);
});
test("A volunteer invitation expires while its response dialog is open", async ({
  page,
}) => {
  await page.clock.install();
  const { errors, requests } = await volunteeringFixture(page);
  await signIn(page);
  await page
    .getByRole("button", { name: "My volunteering", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Respond to invitation", exact: true })
    .filter({ visible: true })
    .click();
  await page.clock.fastForward(3601000);
  await expect(
    page.getByRole("button", { name: "Accept duty", exact: true }),
  ).toBeDisabled();
  await expect(
    page.getByRole("button", { name: "Decline duty", exact: true }),
  ).toBeDisabled();
  await expect(
    page.getByText(
      "This invitation has expired. Refresh your duties to see the latest state.",
    ),
  ).toBeVisible();
  expect(requests.filter((r) => r.method === "POST")).toHaveLength(0);
  expect(errors).toEqual([]);
});

async function familyFixture(page: Page, verified = true, authorized = true) {
  const base = await fixture(page),
    requests: { path: string; method: string; body: any }[] = [];
  let allowed = authorized,
    version = 1;
  const child = { id: "family-child", name: "Amara Mabasa" };
  const session = {
    id: "family-session",
    programme: "Sunday children",
    className: "Juniors",
    room: "Room A",
    location: "Main campus",
    timeZone: "Africa/Johannesburg",
    startsAt: new Date(Date.now() - 300000).toISOString(),
    endsAt: new Date(Date.now() + 3600000).toISOString(),
    status: "OPEN",
    issues: [],
    arrivalWindowOpen: true,
  };
  const visit = {
    id: "family-attendance",
    sessionId: session.id,
    programme: session.programme,
    className: session.className,
    room: session.room,
    location: session.location,
    timeZone: session.timeZone,
    arrivedAt: new Date(Date.now() - 60000).toISOString(),
    collectedAt: null,
    version: 1,
    labelIssued: true,
  };
  await page.route(/\/api\/member\/children/, async (route) => {
    const req = route.request(),
      url = new URL(req.url()),
      body = req.postDataJSON(),
      headers = {
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
        "Access-Control-Allow-Methods": "GET,POST,OPTIONS",
      };
    const respond = (json: unknown, status = 200) =>
      route.fulfill({ json, status, headers });
    if (req.method() === "OPTIONS")
      return route.fulfill({ status: 204, headers });
    requests.push({ path: url.pathname, method: req.method(), body });
    if (url.pathname === "/api/member/children")
      return respond({
        verified,
        items: verified && allowed ? [child] : [],
        hasMore: false,
      });
    if (!allowed)
      return respond({ message: "Authorized child was not found." }, 404);
    if (url.pathname.endsWith("/family-child")) return respond(child);
    if (url.pathname.endsWith("/programmes"))
      return respond({
        verified: true,
        items: [
          {
            id: "programme",
            name: "Sunday children",
            validFrom: "2026-01-01",
            validUntil: "2027-01-01",
            className: "Juniors",
            readiness: [
              { code: "CONSENT", complete: false },
              { code: "GUARDIAN", complete: true },
            ],
            timeZone: session.timeZone,
          },
        ],
        hasMore: false,
      });
    if (url.pathname.endsWith("/sessions"))
      return respond({ verified: true, items: [session], hasMore: false });
    if (url.pathname.endsWith("/attendance"))
      return respond({
        verified: true,
        items: [{ visit: { ...visit, version }, canRequestPickupLabel: true }],
        hasMore: false,
      });
    if (url.pathname.endsWith("/pickup-label")) {
      expect(req.method()).toBe("POST");
      expect(body).toEqual({ expectedVersion: version, replacePrevious: true });
      version++;
      return respond({
        attendanceId: visit.id,
        labelVersion: 2,
        pickupCode: "a".repeat(22),
        expiresAt: new Date(Date.now() + 300000).toISOString(),
        attendanceVersion: version,
      });
    }
    throw new Error("Unexpected family request");
  });
  return {
    ...base,
    requests,
    revoke: () => {
      allowed = false;
    },
  };
}
async function openFamily(page: Page) {
  await signIn(page);
  await page
    .getByRole("button", { name: "My children & family ministry", exact: true })
    .click();
  await page.getByRole("button", { name: "Open Amara Mabasa" }).click();
}
test("Family ministry shows own programmes, readiness and sessions without recording arrival", async ({
  page,
}) => {
  const { errors, requests } = await familyFixture(page);
  await openFamily(page);
  await expect(
    page.getByText("○ Guardian consent", { exact: true }),
  ).toBeVisible();
  await page.screenshot({
    path: "test-results/my-family-390.png",
    fullPage: true,
  });
  await page.getByRole("button", { name: "Sessions", exact: true }).click();
  await expect(page.getByText(/Main campus · Room A/)).toBeVisible();
  await page.getByRole("button", { name: "Show check-in reference" }).click();
  await expect(
    page.getByRole("img", { name: "Child check-in reference QR" }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Hide QR" }).click();
  expect(requests.every((r) => r.method === "GET")).toBe(true);
  expect(errors).toEqual([]);
});
test("Family collection QR requires replacement review, expires and never automatically reissues", async ({
  page,
}) => {
  await page.clock.install();
  const { errors, requests } = await familyFixture(page);
  await openFamily(page);
  await page.getByRole("button", { name: "Attendance", exact: true }).click();
  await page.getByRole("button", { name: "Create collection QR" }).click();
  await expect(
    page.getByText(/invalidates every previous pickup label/),
  ).toBeVisible();
  expect(requests.filter((r) => r.method === "POST")).toHaveLength(0);
  await page.getByRole("button", { name: "Replace label and show QR" }).click();
  await expect(
    page.getByRole("img", { name: "Child collection QR" }),
  ).toBeVisible();
  await page.clock.fastForward(301000);
  await expect(
    page.getByRole("img", { name: "Child collection QR" }),
  ).toHaveCount(0);
  await expect(
    page.getByText(/This code is no longer displayed/),
  ).toBeVisible();
  expect(requests.filter((r) => r.method === "POST")).toHaveLength(1);
  expect(errors).toEqual([]);
});
test("Revoked family authority clears child details on refresh", async ({
  page,
}) => {
  const { errors, revoke } = await familyFixture(page);
  await openFamily(page);
  await page.getByRole("button", { name: "Attendance", exact: true }).click();
  await expect(
    page.getByText("WITH THE MINISTRY TEAM", { exact: true }),
  ).toBeVisible();
  revoke();
  await page
    .getByRole("button", { name: "Refresh", exact: true })
    .filter({ visible: true })
    .click();
  await expect(page.getByText("Authorized child was not found.")).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Create collection QR" }),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});
test("Unverified family accounts have no inferred household authority", async ({
  page,
}) => {
  const { errors } = await familyFixture(page, false, false);
  await signIn(page);
  await page
    .getByRole("button", { name: "My children & family ministry", exact: true })
    .click();
  await expect(
    page.getByText(
      /verify your member identity before viewing family ministry/,
    ),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Open Amara Mabasa" }),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});

async function inboxFixture(page: Page) {
  const base = await fixture(page);
  let read = false;
  let enabled = true;
  let version = 0;
  const mutations: { path: string; body: any }[] = [];
  await page.route(/\/api\/member\/notific/, async (route) => {
    const request = route.request(),
      url = new URL(request.url());
    const headers = {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
      "Access-Control-Allow-Methods": "GET,PUT,OPTIONS",
    };
    if (request.method() === "OPTIONS")
      return route.fulfill({ status: 204, headers });
    if (request.method() === "PUT") {
      const body = request.postDataJSON();
      mutations.push({ path: url.pathname, body });
      if (url.pathname.endsWith("/read")) read = body.read;
      else {
        enabled = body.enabled;
        version++;
      }
      return route.fulfill({ status: 204, headers });
    }
    if (url.pathname === "/api/member/notification-preferences")
      return route.fulfill({
        json: [{ topic: "VOLUNTEERING", enabled, version }],
        headers,
      });
    const items =
      url.searchParams.get("unreadOnly") === "true" && read
        ? []
        : [
            {
              id: "notice-1",
              topic: "VOLUNTEERING",
              title: "You’re invited to serve",
              message: "An invitation is ready for your response.",
              destination: "VOLUNTEERING",
              createdAt: "2026-10-29T08:00:00Z",
              readAt: read ? "2026-10-29T08:05:00Z" : null,
            },
          ];
    return route.fulfill({
      json: { items, unreadCount: read ? 0 : 1, hasMore: false },
      headers,
    });
  });
  return { ...base, mutations };
}
test("member inbox shows unread updates and read state changes only on explicit action", async ({
  page,
}) => {
  const { mutations, errors } = await inboxFixture(page);
  await signIn(page);
  await page
    .getByRole("button", { name: "My inbox · 1 unread", exact: true })
    .click();
  await expect(page.getByText("Your inbox", { exact: true })).toBeVisible();
  await expect(
    page.getByText("You’re invited to serve", { exact: true }),
  ).toBeVisible();
  expect(mutations).toHaveLength(0);
  await page.screenshot({
    path: "test-results/member-inbox-390.png",
    fullPage: true,
  });
  await page.getByRole("button", { name: "Mark as read", exact: true }).click();
  await expect(
    page.getByRole("button", { name: "Mark unread", exact: true }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Unread", exact: true }).click();
  await expect(
    page.getByText("You’re all caught up", { exact: true }),
  ).toBeVisible();
  expect(mutations).toEqual([
    { path: "/api/member/notifications/notice-1/read", body: { read: true } },
  ]);
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  expect(errors).toEqual([]);
});
test("member preferences save a versioned topic change and retain existing inbox notices", async ({
  page,
}) => {
  const { mutations, errors } = await inboxFixture(page);
  await signIn(page);
  await page
    .getByRole("button", { name: "My inbox · 1 unread", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Notification preferences", exact: true })
    .click();
  await page
    .getByRole("button", {
      name: "Turn off Serving & volunteering",
      exact: true,
    })
    .click();
  await expect(
    page.getByText("Inbox updates off", { exact: true }),
  ).toBeVisible();
  expect(mutations).toEqual([
    {
      path: "/api/member/notification-preferences/VOLUNTEERING",
      body: { enabled: false, expectedVersion: 0 },
    },
  ]);
  await page
    .getByRole("button", { name: "Back to inbox", exact: true })
    .click();
  await expect(
    page
      .getByText("You’re invited to serve", { exact: true })
      .filter({ visible: true }),
  ).toBeVisible();
  expect(errors).toEqual([]);
});
test("inbox opens the member workflow without performing an invitation response", async ({
  page,
}) => {
  const { mutations, errors } = await inboxFixture(page);
  await signIn(page);
  await page
    .getByRole("button", { name: "My inbox · 1 unread", exact: true })
    .click();
  await page
    .getByRole("button", { name: "View current details", exact: true })
    .click();
  await expect(page).toHaveURL(/\/volunteering$/);
  expect(mutations).toEqual([]);
  expect(errors).toEqual([]);
});

test("a failed inbox update remains unread and is never retried automatically", async ({
  page,
}) => {
  await inboxFixture(page);
  let writes = 0;
  await page.route(
    /\/api\/member\/notifications\/notice-1\/read$/,
    async (route) => {
      const headers = {
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
        "Access-Control-Allow-Methods": "PUT,OPTIONS",
      };
      if (route.request().method() === "OPTIONS")
        return route.fulfill({ status: 204, headers });
      writes++;
      return route.fulfill({
        status: 503,
        json: { message: "Unable to save. Refresh before trying again." },
        headers,
      });
    },
  );
  await signIn(page);
  await page
    .getByRole("button", { name: "My inbox · 1 unread", exact: true })
    .click();
  await page.getByRole("button", { name: "Mark as read", exact: true }).click();
  await expect(
    page.getByText("Unable to save. Refresh before trying again.", {
      exact: true,
    }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Mark as read", exact: true }),
  ).toBeEnabled();
  expect(writes).toBe(1);
});

test("a preference conflict refreshes the current revision without repeating the change", async ({
  page,
}) => {
  await inboxFixture(page);
  let writes = 0;
  await page.route(
    /\/api\/member\/notification-preferences\/VOLUNTEERING$/,
    async (route) => {
      const headers = {
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
        "Access-Control-Allow-Methods": "PUT,OPTIONS",
      };
      if (route.request().method() === "OPTIONS")
        return route.fulfill({ status: 204, headers });
      writes++;
      return route.fulfill({
        status: 409,
        json: {
          message: "Notification preferences changed. Refresh and try again.",
        },
        headers,
      });
    },
  );
  await signIn(page);
  await page
    .getByRole("button", { name: "My inbox · 1 unread", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Notification preferences", exact: true })
    .click();
  await page
    .getByRole("button", {
      name: "Turn off Serving & volunteering",
      exact: true,
    })
    .click();
  await expect(
    page.getByText("Notification preferences changed. Refresh and try again.", {
      exact: true,
    }),
  ).toBeVisible();
  await expect(
    page.getByText("Inbox updates on", { exact: true }),
  ).toBeVisible();
  expect(writes).toBe(1);
});

async function pushFixture(page: Page, available = true) {
  const base = await inboxFixture(page);
  let enabled = false,
    version = 0,
    active = true;
  const mutations: { path: string; body: any }[] = [];
  await page.route(/\/api\/member\/push(?:\/|$)/, async (route) => {
    const request = route.request(),
      path = new URL(request.url()).pathname;
    const headers = {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Headers": "Authorization,Content-Type,Accept",
      "Access-Control-Allow-Methods": "GET,PUT,DELETE,OPTIONS",
    };
    if (request.method() === "OPTIONS")
      return route.fulfill({ status: 204, headers });
    if (request.method() === "PUT") {
      const body = request.postDataJSON();
      mutations.push({ path, body });
      enabled = body.enabled;
      version++;
      return route.fulfill({ status: 204, headers });
    }
    if (request.method() === "DELETE") {
      mutations.push({ path, body: null });
      active = false;
      return route.fulfill({ status: 204, headers });
    }
    return route.fulfill({
      headers,
      json: {
        available,
        projectId: available ? "11111111-1111-4111-8111-111111111111" : null,
        preferences: [{ topic: "VOLUNTEERING", enabled, version }],
        devices: [
          {
            id: "11111111-2222-4333-8444-555555555555",
            platform: "android",
            status: active ? "ACTIVE" : "REVOKED",
            currentSession: false,
            expiresAt: "2026-10-30T00:00:00Z",
            updatedAt: "2026-10-29T00:00:00Z",
          },
        ],
      },
    });
  });
  return { ...base, pushMutations: mutations };
}
test("push topic preferences are versioned independently from inbox choices", async ({
  page,
}) => {
  const { pushMutations, errors } = await pushFixture(page);
  await signIn(page);
  await page
    .getByRole("button", { name: "My inbox · 1 unread", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Notification preferences", exact: true })
    .click();
  await page
    .getByRole("button", {
      name: "Turn on push for Serving & volunteering",
      exact: true,
    })
    .click();
  await expect(page.getByText("Push alerts on", { exact: true })).toBeVisible();
  await expect(
    page.getByText("Inbox updates on", { exact: true }),
  ).toBeVisible();
  expect(pushMutations).toEqual([
    {
      path: "/api/member/push/preferences/VOLUNTEERING",
      body: { enabled: true, expectedVersion: 0 },
    },
  ]);
  await expect(
    page.getByRole("button", {
      name: "Enable notifications on this device",
      exact: true,
    }),
  ).toHaveCount(0);
  expect(errors).toEqual([]);
});
test("members can revoke their own push registrations without exposing tokens", async ({
  page,
}) => {
  const { pushMutations } = await pushFixture(page);
  await signIn(page);
  await page
    .getByRole("button", { name: "My inbox · 1 unread", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Notification preferences", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Revoke this push registration", exact: true })
    .click();
  await expect(page.getByText(/Another sign-in · revoked/)).toBeVisible();
  expect(pushMutations).toEqual([
    {
      path: "/api/member/push/devices/11111111-2222-4333-8444-555555555555",
      body: null,
    },
  ]);
});
test("an unconfigured push service leaves the member inbox usable", async ({
  page,
}) => {
  const { pushMutations } = await pushFixture(page, false);
  await signIn(page);
  await page
    .getByRole("button", { name: "My inbox · 1 unread", exact: true })
    .click();
  await page
    .getByRole("button", { name: "Notification preferences", exact: true })
    .click();
  await expect(
    page.getByText(
      "Your church is finishing device push setup. Your inbox remains available.",
      { exact: true },
    ),
  ).toBeVisible();
  expect(pushMutations).toEqual([]);
  await page
    .getByRole("button", { name: "Back to inbox", exact: true })
    .click();
  await expect(
    page
      .getByText("You’re invited to serve", { exact: true })
      .filter({ visible: true }),
  ).toBeVisible();
});
