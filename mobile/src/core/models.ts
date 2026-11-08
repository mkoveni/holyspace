export interface Account {
  id: string;
  displayName: string;
  email: string;
  username: string;
  status: string;
}
export interface Contact {
  id: string;
  kind: "EMAIL" | "PHONE";
  value: string;
  primary: boolean;
}
export interface Profile {
  id: string;
  details: {
    titleCode: string | null;
    firstName: string;
    lastName: string;
    dateOfBirth: string | null;
    gender: "MALE" | "FEMALE" | "UNKNOWN";
  };
  membershipType: string;
  version: number;
  contacts: Contact[];
}
export interface MemberProfile {
  profile: Profile | null;
  titles: { code: string; label: string }[];
}
export interface Fund {
  id: string;
  name: string;
  description: string;
}
export interface Provider {
  id: string;
  name: string;
  available: boolean;
  mode: string;
  recurring: boolean;
}
export interface Payment {
  id: string;
  fundId: string;
  amountMinor: number;
  provider: string;
  mode: string;
  recurring: boolean;
  status: string;
  subscriptionStatus: string;
  version: number;
  createdAt: string;
}
export interface Giving {
  donor: { id: string; name: string } | null;
  funds: Fund[];
  providers: Provider[];
  payments: { items: Payment[]; total: number };
  gifts: {
    id: string;
    amountMinor: number;
    currency: string;
    receivedOn: string;
    method: string;
    reversed: boolean;
    refundedMinor: number;
  }[];
  giftTotal: number;
}
export interface PaymentDetail {
  order: Payment;
  charges: {
    id: string;
    amountMinor: number;
    refundedMinor: number;
    receivedOn: string;
    givingStatus: string;
    confirmationStatus: string;
    captured: boolean;
    confirmation: { subject: string; html: string } | null;
  }[];
  refunds: { amountMinor: number; refundedOn: string; applied: boolean }[];
}
export interface Device {
  id: string;
  deviceName: string;
  current: boolean;
  createdAt: string;
  expiresAt: string;
}
export interface GiftIntent {
  requestId: string;
  fundId: string;
  amountMinor: number;
  provider: string;
  recurring: boolean;
}

export interface MemberEvent {
  id: string;
  eventId: string;
  title: string;
  description: string;
  startsAt: string;
  endsAt: string;
  timeZone: string;
  venueName: string;
  venueAddress: string;
  status: string;
  registrationOpen: boolean;
  waitlistEnabled: boolean;
  remainingPlaces: number | null;
  booking: {
    id: string;
    status: string;
    version: number;
    attendance: string | null;
  } | null;
  /** Same-origin public image path, or null when staff set no cover. Resolve with `coverImageSource`. */
  coverImageUrl: string | null;
}
export interface MemberEvents {
  verified: boolean;
  items: MemberEvent[];
  hasMore: boolean;
}
export interface EventPass {
  credential: string;
  expiresAt: string;
}

export interface MemberCommunity {
  id: string;
  kind: "MINISTRY" | "GROUP";
  name: string;
  description: string;
  campus: string;
  parentName: string | null;
  joinedAt: string;
  role: string;
  /** Same-origin public image path, or null when staff set no cover. Resolve with `coverImageSource`. */
  coverImageUrl: string | null;
}
export interface MyCommunities {
  verified: boolean;
  items: MemberCommunity[];
  hasMore: boolean;
}
export interface MyCommunity {
  community: MemberCommunity;
  leaders: { name: string; role: string }[];
  hasMoreLeaders: boolean;
}
export interface MemberMeeting {
  id: string;
  communityId: string;
  communityName: string;
  startsAt: string;
  endsAt: string;
  timeZone: string;
  venue: string;
  status: string;
  attendance: string;
}
export interface MyMeetings {
  verified: boolean;
  items: MemberMeeting[];
  hasMore: boolean;
}
export type WeeklyAvailability = {
  dayOfWeek: number;
  startsAt: string;
  endsAt: string;
  timeZone: string;
  validFrom: string;
  validUntil: string;
};
export type AvailabilityException = {
  startsAt: string;
  endsAt: string;
  available: boolean;
};
export type VolunteerDuty = {
  id: string;
  status: string;
  shiftStatus: string;
  team: string;
  role: string;
  activity: string | null;
  location: string | null;
  startsAt: string;
  endsAt: string;
  readiness: { ready: boolean; issues: string[] };
  invitation: {
    id: string;
    version: number;
    status: string;
    expiresAt: string;
  } | null;
};
export type MyVolunteering = {
  volunteerId: string | null;
  permitted: boolean;
  linked: boolean;
  status: string | null;
  version: number | null;
  weekly: WeeklyAvailability[];
  exceptions: AvailabilityException[];
  items: VolunteerDuty[];
  hasMore: boolean;
};
export type FamilyPage<T> = { verified: boolean; items: T[]; hasMore: boolean };
export type FamilyChild = { id: string; name: string };
export type FamilyProgramme = {
  id: string;
  name: string;
  validFrom: string;
  validUntil: string;
  className: string | null;
  readiness: { code: string; complete: boolean }[];
  timeZone: string;
};
export type FamilySession = {
  id: string;
  programme: string;
  className: string;
  room: string;
  location: string;
  timeZone: string;
  startsAt: string;
  endsAt: string;
  status: string;
  issues: string[];
  arrivalWindowOpen: boolean;
};
export type FamilyVisit = {
  visit: {
    id: string;
    sessionId: string;
    programme: string;
    className: string;
    room: string;
    location: string;
    timeZone: string;
    arrivedAt: string;
    collectedAt: string | null;
    version: number;
    labelIssued: boolean;
  };
  canRequestPickupLabel: boolean;
};
export type FamilyLabel = {
  attendanceId: string;
  labelVersion: number;
  pickupCode: string;
  expiresAt: string;
  attendanceVersion: number;
};

export type MemberNotification = {
  id: string;
  topic: string;
  title: string;
  message: string;
  destination: string;
  createdAt: string;
  readAt: string | null;
};
export type MemberInbox = {
  items: MemberNotification[];
  unreadCount: number;
  hasMore: boolean;
};
export type NotificationPreference = {
  topic: string;
  enabled: boolean;
  version: number;
};
