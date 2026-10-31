import { notificationDestination } from "./notifications.ts";
export type PushPreference = {
  topic: string;
  enabled: boolean;
  version: number;
};
export type PushDevice = {
  id: string;
  platform: string;
  status: string;
  currentSession: boolean;
  expiresAt: string;
  updatedAt: string;
};
export type PushSettings = {
  available: boolean;
  projectId: string | null;
  preferences: PushPreference[];
  devices: PushDevice[];
};
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
export function pushNotificationId(data: unknown): string | null {
  if (!data || typeof data !== "object") return null;
  const value = data as Record<string, unknown>;
  return value.type === "holyspace.member-update.v1" &&
    typeof value.notificationId === "string" &&
    uuid.test(value.notificationId)
    ? value.notificationId
    : null;
}
export function canRenewPush(
  savedAccount: string | null,
  currentAccount: string,
  devices: PushDevice[],
  installationId: string,
): boolean {
  return (
    savedAccount === currentAccount &&
    devices.some((d) => d.id === installationId && d.status !== "REVOKED")
  );
}

export async function resolvePushDestination(
  data: unknown,
  load: (id: string) => Promise<{ destination: string }>,
  isCurrent: () => boolean,
): Promise<"/volunteering" | "/events" | null> {
  const id = pushNotificationId(data);
  if (!id || !isCurrent()) return null;
  try {
    const notice = await load(id);
    return isCurrent() ? notificationDestination(notice.destination) : null;
  } catch {
    return null;
  }
}
